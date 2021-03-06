<?php

/*
 * (c) Logomotion <production@logomotion.fr>
 *
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Cart;
use AppBundle\Entity\Cartline;
use AppBundle\Entity\Tax;
use AppBundle\Entity\Tpays;
use AppBundle\Service\CountryService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface as Translator;
use AppBundle\Entity\Commande;
use AppBundle\Entity\Produit;
use AppBundle\Entity\Comprd;
use FOS\RestBundle\Controller\Annotations as Rest;
use AppBundle\Repository\CommandeRepository;
use AppBundle\Repository\ProductRepository;
use AppBundle\Repository\CartRepository;
use AppBundle\Repository\TaxRepository;
use AppBundle\Repository\ShippingRepository;
use AppBundle\Repository\TpaysRepository;
use AppBundle\Repository\ClientRepository;
use AppBundle\Service\Mailer;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use SensioLabs\Security\Exception\HttpException;
use AppBundle\Service\PaypalService;
use AppBundle\Service\PageService;
use AppBundle\Service\CartService;
use AppBundle\Service\PaymentService;

class OrderController extends Controller
{
    const TVA = 5.5;
    const AUTHORIZED_PAYMENT_IP_ADRESSES = [
        '195.101.99.73',
        '195.101.99.76',
        '194.2.160.80',
        '194.2.160.82',
        '194.2.160.91',
        '195.25.67.0',
        '195.25.67.2',
        '195.25.67.11',
        '194.2.122.190',
        '195.25.67.22',
        '127.0.0.1'
    ];
    const TVASHIPMENT = 20;

    const FREE_SHIPPING_EXCEPTION = [
        'Roche',
        'Font',
    ];

    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var CommandeRepository
     */
    private $commandeRepository;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var TaxRepository
     */
    private $taxRepository;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ShippingRepository
     */
    private $shippingRepository;

    /**
     * @var TpaysRepository
     */
    private $tpaysRepository;

    /**
     * @var CartRepository
     */
    private $cartRepository;

    /**
     * @var ClientRepository
     */
    private $clientRepository;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var PaymentService
     */
    private $paymentService;

    public function __construct(
        CommandeRepository $commandeRepository,
        ProductRepository $productRepository,
        CartRepository $cartRepository,
        ClientRepository $clientRepository,
        ShippingRepository $shippingRepository,
        TpaysRepository $tpaysRepository,
        Mailer $mailer,
        Translator $translator,
        LoggerInterface $logger,
        EntityManagerInterface $em,
        PageService $pageService,
        CartService $cartService,
        PaymentService $paymentService,
        TaxRepository $taxRepository
    ) {
        $this->commandeRepository = $commandeRepository;
        $this->productRepository = $productRepository;
        $this->tpaysRepository = $tpaysRepository;
        $this->cartRepository = $cartRepository;
        $this->clientRepository = $clientRepository;
        $this->shippingRepository = $shippingRepository;
        $this->mailer = $mailer;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->em = $em;
        $this->pageService = $pageService;
        $this->cartService = $cartService;
        $this->paymentService = $paymentService;
        $this->taxRepository = $taxRepository;
    }

    /**
     * @Rest\Get("/xhr/order/weight/{weight}/{country}", name="get_weight")
     * @Rest\View()
    */
    public function xhrTestWeight(Request $request, $weight, $country)
    {
        return $this->findShipping($weight, $country);
    }

    /**
     * @Rest\Get("/xhr/order/cartcount", name="get_cartcount")
     * @Rest\View()
    */
    public function xhrGetCartCount(Request $request)
    {
        return ['status' => 'ok' , 'data' => $this->cartService->getCartCount($request->cookies->get('cart'))];
    }

    /**
     * @Rest\Get("/xhr/order/vat/{vat}", name="get_vat")
     * @Rest\View()
    */
    public function xhrTestVAT(Request $request, $vat)
    {
        try {
            $vat = str_replace(' ', '', $vat);
            $client = new \SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl");
            $result = $client->checkVat(array(
                'countryCode' => substr($vat, 0, 2),
                'vatNumber' => substr($vat, 2, 11)
            ));
            if ($result->valid) {
                return ['status' => 'ok'];
            }
        } catch (\Exception $exception) {
            // If VAT Service is broken, considering everything is OK (bullshit but whatever)
            ['status' => 'ok'];
        }

        return ['status' => 'ko','error' => 'your tvaIntra didnt exist'];
    }

    /**
     * @Rest\Get("/xhr/order/zipcode/{country}/{zipcode}/{destliv}", name="get_zipcode")
     * @Rest\View()
    */
    public function xhrCheckZipcode(Request $request, $country, $zipcode, $destliv)
    {
        $value = $this->tpaysRepository->checkZipcode($country, $zipcode, $destliv);
        if ($value === true) {
            return ['status' => 'ok'];
        } else {
            return ['status' => 'ko','error' => 'your zipcode doesn\'t exist'];
        }
    }

    /**
     * @Rest\Get("/xhr/order/taxes/{cart_id}/{country}/{destliv}", name="get_taxes")
     * @Rest\View()
    */
    public function xhrGetTaxes(Request $request, $cart_id, $country, $destliv)
    {
        try {
            $data = $this->getCartPrices($cart_id, $country, $destliv);
            return ['status' => 'ok','data' => $data];
        } catch (\Exception $exception) {
            return ['status' => 'ko', 'message' => $exception->getMessage()];
        }
    }


    /**
     * @Rest\Post("xhr/order/client", name="post_client")
     * @Rest\View()
     */
    public function xhrPostClientAction(Request $request)
    {
        $client = $request->get('client');
        if (!$client) {
            return ['status' => 'ko', 'message' => 'You must provide a client object'];
        }
        $em = $this->getDoctrine()->getManager();
        $customer = $this->clientRepository->findClient($client['codcli']);
        $customer->setNom($client['nom'])
        ->setPrenom($client['prenom'])
        ->setCivil($client['civil'])
        ->setAdresse($client['adresse'])
        ->setCp($client['cp'])
        ->setVille($client['ville'])
        ->setPays($client['pays'])
        ->setTel($client['tel'])
        ->setMobil($client['mobil'])
        ->setEmail($client['email'])
        ->setSociete($client['societe'])
        ->setProfessionnel($client['professionnel']);
        $em->persist($customer);
        $em->flush();
        return ['status' => 'ok', 'data' => $customer];
    }

    private function getCartPrices($cartId, $country, $destliv)
    {
        /** @var Cart $cart */
        $cart = $this->cartRepository->find($cartId);
        $data = [];
        $data['totalPrice'] = 0;
        $data['totalPriceIT'] = 0;
        $totalWeight = 0;

        /** @var Cartline $cartline */
        foreach ($cart->getCartlines() as $k => $cartline) {
            $i = 1;
            while ($i <= $cartline->getQuantity()) {
                $product = $cartline->getProduct();
                /** @var Tax $tax */
                $tax = $this->taxRepository->findTax($product->getTypprd(), $country);
                $productTaxRate = ($tax) ? $tax->getRate() : 0;
                $priceIncludeTaxes = floatval(round($product->getPrix(), 2));
                $priceHT = floatval(round($priceIncludeTaxes / (1 + ($productTaxRate / 100)), 2));

                // R??gle pour les CD
                // Si la TVA vaut 0, le prix HT est le prix TTC
                if ($product->getTypprd() === Produit::TYPE_CD && 0 === $productTaxRate) {
                    $priceHT = floatval($product->getPrix());
                }

                $data['product'][$k]['productTaxRate'] = $productTaxRate;
                $data['product'][$k]['price'] = $priceHT;
                $data['totalPrice'] = round($data['totalPrice'] + $data['product'][$k]['price'], 2);
                $data['product'][$k]['codprd'] = $product->getCodprd();
                $data['product'][$k]['quantity'] = $cartline->getQuantity();
                $data['product'][$k]['name'] = $product->getProduitcourt();
                $data['product'][$k]['priceIT'] = $priceIncludeTaxes;
                $data['product'][$k]['vatProduct'] = round($data['product'][$k]['priceIT'] - $data['product'][$k]['price'], 2);

                $data['totalPriceIT'] = round($data['totalPriceIT'] + $priceIncludeTaxes, 2);
                $totalWeight = $totalWeight + $product->getPoids();

                $i++;
            }
        }

        $shippingPriceData = $this->findShipping($totalWeight, $country, $destliv);
        $packagingWeight = $shippingPriceData['supplementWeight'];
        $shippingPriceIT = $shippingPriceData['price'];
        $data['maxWeight'] = $shippingPriceData['maxWeight'];
        $data['packagingWeight'] = $packagingWeight;
        $data['weightOrder'] = $totalWeight;
        $data['totalWeight'] = $totalWeight + $packagingWeight;
        $data['shippingPriceIT'] = $shippingPriceIT;
        $data['consumerPriceIT'] = $data['shippingPriceIT'] + $data['totalPriceIT'];
        $data['consumerPrice'] = $data['shippingPriceIT'] + $data['totalPrice'];

        $data['vat'] = round($data['totalPriceIT'] - $data['totalPrice'], 2);

        return $data;
    }

    private function findShipping($weight, $country, $destliv = null)
    {
        if (in_array($destliv, $this::FREE_SHIPPING_EXCEPTION)) {
            return ['supplementWeight' => 0, 'price' => 0];
        }
        list($supplementWeight, $maxWeight) = $this->shippingRepository->findWeight($weight, $country);
        $weight += $supplementWeight;

        $price = $this->shippingRepository->findShipping($weight, $country);
        return ['supplementWeight' => $supplementWeight, 'price' => $price['price'], 'maxWeight' => $maxWeight];
    }

    /**
     * @Rest\Post("/order/delivery", name="post_delivery")
     * @Rest\View()
    */
    public function xhrPostAddrDeliveryAction(Request $request)
    {
        $cookies = $request->cookies;
        $cartId = $cookies->get('cart');
        $locale = $request->getLocale();

        $delivery = $request->get('delivery');
        if ($this->getUser() === null) {
            $user = $this->clientRepository->findClient($delivery['codcli']);
            $email = $delivery['email'];
        } else {
            $user = $this->getUser();
            $email = $user->getEmail();
            $delivery['email'] = $email;
        }
        $delivery['nom'] = $delivery['adliv']['nom'];
        $delivery['prenom'] = $delivery['adliv']['prenom'] ;
        $delivery['civil'] = $delivery['adliv']['civil'];
        if (!$delivery) {
            return ['status' => 'ko', 'message' => 'You must provide delivery object'];
        }
        if (!isset($delivery['adliv'])) {
            return ['status' => 'ko', 'message' => 'You must provide a client with a delivery Adresse'];
        }
        if (!isset($delivery['destliv'])) {
            return ['status' => 'ko', 'message' => 'You must provide a client with a delivery destination'];
        }
        if (!isset($delivery['paysliv'])) {
            return ['status' => 'ko', 'message' => 'You must provide a client with a delivery country'];
        }
        if (!isset($delivery['modpaie'])) {
            return ['status' => 'ko', 'message' => 'You must provide a client with a delivery mode paiement'];
        }
        if (!isset($delivery['modliv'])) {
            return ['status' => 'ko', 'message' => 'You must provide a client with a delivery mode livraison'];
        }
        if (!isset($delivery['validpaie'])) {
            return ['status' => 'ko', 'message' => 'You must provide a client with a delivery validpaie'];
        }
        if (!isset($delivery['datliv'])) {
            return ['status' => 'ko', 'message' => 'You must provide a client with a delivery datliv'];
        }
        if (!isset($delivery['paysip'])) {
            return ['status' => 'ko', 'message' => 'You must provide a client with a delivery paysip'];
        }
        if (!isset($delivery['dateenreg'])) {
            return ['status' => 'ko', 'message' => 'You must provide a client with a delivery dateenreg'];
        }
        if (!isset($delivery['cartId'])) {
            return ['status' => 'ko', 'message' => 'You must provide a client with a delivery cartid'];
        }
        if (!isset($delivery['modpaie'])) {
            return ['status' => 'ko', 'data' => 'payment.modpaie_unknown'];
        }
        if ($order = $this->registerOrder($delivery, $cartId, $locale, $user, $email)) {
            $paymentUrl = $this->paymentService->getUrl(
                $delivery['modpaie'],
                $order->getTtc(),
                $order->getRefcom(),
                $this->translator->trans('order.payment.title'),
                $email,
                $locale,
                'order',
                '',
                '',
                $delivery,
                $order
            );
            return ['status' => 'ok', 'data' => $paymentUrl];
        }
        return ['status' => 'ko', 'message' => 'an error as occured'];
    }

    /**
     *
     * @Route("/{_locale}/order/paymentcheque-return/{ref}", name="order_paymentcheque_return")
     */
    public function paymentChequeReturnAction($ref, Request $request)
    {
        $cookies = $request->cookies;
        $cartId = $cookies->get('cart');
        $cart = $this->cartRepository->find($cartId);

        if ($cart) {
            $this->em->remove($cart);
            $this->em->flush();
            $response = new Response;
            $response->headers->clearCookie('cart');
            $response->send();
        }

        /** @var Commande $commande */
        $commande = $this->commandeRepository->findByRef($ref);
        $dataOrder = $this->getDataOrderForNotify($commande);
        $user = $this->clientRepository->findClient($commande->getCodcli());
        if ($commande === null || $user == null) {
            return $this->render('order/payment-return.html.twig', [
                'chequemessage' => true,
                'status' => 'error',
            ]);
        }
        if ($commande->getModpaie() !== 'ICH'
            || $user->getCodcli() !== $commande->getCodcli()) {
            return $this->render('order/payment-return.html.twig', [
                'chequemessage' => true,
                'status' => 'error',
            ]);
        }

        $locale = $request->getLocale();

        if ($commande->getDestLiv() === "Roche") {
            $addCom = $this->translator->trans('order.notify.client.roche.adliv');
            $withDelay = false;
        } elseif ($commande->getDestLiv() === "Font") {
            $addCom = $this->translator->trans('order.notify.client.font.adliv');
            $withDelay = false;
        } else {
            $addCom = $commande->getAdLiv();
            if (empty($addCom['Societe'])) {
                $adLiv = [
                    $addCom['Civil'],
                    $addCom['Prenom'],
                    $addCom['Nom'] . '<br>',
                    nl2br($addCom['Adresse']) . '<br>',
                    $addCom['CP'],
                    $addCom['Ville'] . '<br>',
                    $this->getCountryFullname($addCom['Pays'])
                ];
            } else {
                $adLiv = [
                    $addCom['Societe'] . '<br>',
                    $addCom['Civil'],
                    $addCom['Prenom'],
                    $addCom['Nom'] . '<br>',
                    nl2br($addCom['Adresse']) . '<br>',
                    $addCom['CP'],
                    $addCom['Ville'] . '<br>',
                    $this->getCountryFullname($addCom['Pays'])
                ];
            }
            $addCom = join(' ', $adLiv);
            $withDelay = true;
        }

        $paysliv = $this
        ->tpaysRepository
        ->findCountryByCode($commande->getAdFact()['Pays']);
        $minliv = $paysliv->getMinliv();
        $maxliv = $paysliv->getMaxliv();

        if ($user->getEmail() === null) {
            $email = $commande->getAdLiv()['email'];
        } else {
            $email = $user->getEmail();
        }

        if ($commande->getValidpaie() !== 'enAttente') {
            $this->mailer->send(
                [
                    $email
                ],
                $this->translator->trans('order.notify.client.subject'),
                $this->renderView('emails/order-notify-cheque-order-'.$locale.'.html.twig', [
                    'order' => $commande,
                    'adliv' => $addCom,
                    'minliv' => $minliv,
                    'maxliv' => $maxliv,
                    'withDelay' => $withDelay,
                    'dataOrder' => $dataOrder
                    ])
            );

            $this->mailer->send(
                $this->getParameter('email_from_address'),
                $this->translator->trans('order.notify.client.subject'),
                $this->renderView('emails/order-notify-cheque-order-ro.html.twig', [
                    'order' => $commande,
                    'user' => $user,
                    'minliv' => $minliv,
                    'maxliv' => $maxliv
                    ])
            );
        }

        $commande->setValidpaie('enAttente');
        $this->em->persist($commande);
        $this->em->flush();

        return $this->render('order/payment-return.html.twig', [
            'chequemessage' => true,
            'status' => 'succes',
            'refCom' => $commande->getRefCom(),
            'addCom' =>  $addCom,
            'minliv' => $minliv,
            'maxliv' => $maxliv,
            'withDelay' => $withDelay
        ]);
    }

    /**
     *
     * @Route("/{_locale}/order/payment-return/{method}/{status}", name="order_payment_return")
     */
    public function paymentReturnAction($method, $status, Request $request, PaypalService $paypalService)
    {
        $cookies = $request->cookies;
        $cartId = $cookies->get('cart');
        $cart = $this->cartRepository->find($cartId);

        if ($status === 'cancel' || $status === 'error') {
            return $this->redirectToRoute('order-'.$request->getLocale(), ['orderId' => $request->query->get('Ref')]);
        }

        if ($status === 'success') {
            if ($cart) {
                $this->em->remove($cart);
                $this->em->flush();
                $response = new Response;
                $response->headers->clearCookie('cart');
                $response->send();
            }
        }

        if (null !== $request->query->get('Ref')) {
            /** @var Commande $commande */
            $commande = $this
                        ->commandeRepository
                        ->findByRef($request->query->get('Ref'));

            if ($commande->getDestLiv() === "Roche") {
                $addCom = $this->translator->trans('order.notify.client.roche.adliv');
                $withDelay = false;
            } elseif ($commande->getDestLiv() === "Font") {
                $addCom = $this->translator->trans('order.notify.client.font.adliv');
                $withDelay = false;
            } else {
                $addCom = $commande->getAdLiv();
                $addCom = join('', [
                    empty($addCom['Societe']) ? '' : ($addCom['Societe'] . '<br>'),
                    $addCom['Civil'] . ' ' . $addCom['Prenom'] . ' ' . $addCom['Nom'] . '<br>',
                    nl2br($addCom['Adresse']) . '<br>',
                    $addCom['CP'] . ' ' . $addCom['Ville'] . '<br>',
                    $this->getCountryFullname($addCom['Pays'])
                ]);
                $withDelay = true;
            }

            $paysliv = $this
            ->tpaysRepository
            ->findCountryByCode($commande->getAdFact()['Pays']);
            $minliv = $paysliv->getMinliv();
            $maxliv = $paysliv->getMaxliv();

            return $this->render('order/payment-return.html.twig', [
                'refCom' => $commande->getRefCom(),
                'addCom' =>  $addCom,
                'status' => $status,
                'method' => $method,
                'minliv' => $minliv,
                'maxliv' => $maxliv,
                'withDelay' => $withDelay
            ]);
        } else {
            return $this->render('order/payment-return.html.twig', [
                'status' => $status,
                'method' => $method,
                'withDelay' => false
            ]);
        }
    }

    /**
     * @Route("/{_locale}/order/payment-notify/{method}", name="order_payment_notify")
     */
    public function paymentNotifyAction($method, Request $request, PaypalService $paypalService)
    {
        $this->logger->info($request);
        if ($method === 'paybox') {
            if (!in_array($request->getClientIp(), $this::AUTHORIZED_PAYMENT_IP_ADRESSES)) {
                $this->logger->alert('Bad IP address used for payment return: '.$request->getClientIp());
                throw new HttpException('Bad IP address used for payment return');
            }
            $ref = $request->get('Ref');
            $status = ($request->get('Erreur') === '00000') ? $request->get('Trans') : false;
            $country = $request->get('Pays');
            $amount = ($request->get('Amount')/100);
        } elseif ($method === 'paypal') {
            if ($this->getParameter('payment_env') === 'dev') {
                $paypalService->useSandbox();
            }
            if ($paypalService->verifyIPN()) {
                $ref = $request->get('item_number');
                $status = $request->get('ipn_track_id');
                $country = $request->get('residence_country');
                $amount = (floatval($request->get('mc_gross')));
                $this->logger->info($status);
            } else {
                $this->logger->alert('Paypal IPN verification failed');
                throw new HttpException('Paypal IPN verification failed');
            }
        }

        $order = $this->commandeRepository->findByRef($ref);
        if ($status && $order->getTtc() == $amount) {
            $user = $this->clientRepository->findClient($order->getCodcli());
            $locale = $request->getLocale();
            $order->setDatpaie(new \DateTime())
            ->setValidpaie($status)
            ->setPaysIP($country);
            $this->em->persist($order);
            $this->em->flush();
            if ($order->getDestliv() !== 'myAd' || $order->getDestliv() !== 'Other') {
                $withDelay = true;
            } else {
                $withDelay = false;
            }
            $this->notifyClient($order, $locale, $user, $withDelay);
        } else {
            $order->setDatpaie(new \DateTime('0000-00-00'))
            ->setValidpaie('error')
            ->setPaysIP($country);
            $this->em->persist($order);
            $this->em->flush();
        }
        return new Response('ok');
    }

    private function registerOrder($delivery, $cartId, $locale, $user, $email)
    {
        $codcli = $user->getCodcli();
        $cart = $this->cartRepository->find($delivery['cartId']);

        $datCom = new \DateTime();
        $modpaie = $delivery['modpaie'];
        $modliv = $delivery['modliv'];
        $adfact = [
            'Civil' => $user->getCivil(),
            'Nom' => $user->getNom(),
            'Prenom' => $user->getPrenom(),
            'Adresse' => $user->getAdresse(),
            'CP' => $user->getCp(),
            'Rue' => $user->getRue(),
            'Ville' => $user->getVille(),
            'Pays' => $user->getPays(),
            'Tel' => $user->getTel(),
            'Mobil' => $user->getMobil(),
            'Professionnel' => $user->getProfessionnel(),
            'Societe' => $user->getSociete(),
            'TvaIntra' => $user->getTvaintra(),
            'MemoCli' => $user->getMemocli(),
            'Email' => $email
        ];

        try {
            $data = $this->getCartPrices($delivery['cartId'], $delivery['paysliv'], $delivery['destliv']);
        } catch (\Exception $exception) {
            return ['status' => 'ko', 'message' => $exception->getMessage()];
        }

        $datpaie = new \DateTime('0000-00-00 00:00:00');
        $validpaie = $delivery['validpaie'];
        $destliv = $delivery['destliv'];
        $delivery['adliv']['Pays'] = $delivery['paysliv'];
        $delivery['adliv']['Email'] = $delivery['email'];
        $adliv = [
            'Societe' => $delivery['adliv']['societe'],
            'Civil' => $delivery['adliv']['civil'],
            'Nom' => $delivery['adliv']['nom'],
            'Prenom' => $delivery['adliv']['prenom'],
            'Adresse' => $delivery['adliv']['adresse'],
            'CP' => $delivery['adliv']['zipcode'],
            'Ville' => $delivery['adliv']['city'],
            'Pays' => $delivery['adliv']['Pays']
        ];
        if (!isset($delivery['memocmd'])) {
            $memoCmd = "";
        } else {
            $memoCmd = $delivery['memocmd'];
        }

        $weight = $data['weightOrder'];
        $shippingPrice = $data['shippingPriceIT'];
        $amountHT = $data['consumerPrice'];
        $promo = 0;
        $priceit = $data['consumerPriceIT'];
        $vat = $data['vat'];

        $paysip = $delivery['paysip'];

        $em = $this->getDoctrine()->getManager();
        $order = new Commande;

        $order->setRefcom($this->commandeRepository->generateRefCom());
        $order->setCodcli($codcli);
        $order->setDatcom($datCom);
        $order->setMontant($amountHT);
        $order->setModpaie($modpaie);
        $order->setModliv($modliv);
        $order->setDatpaie($datpaie);
        $order->setValidpaie($validpaie);
        $order->setDestliv($destliv);
        $order->setAdLiv($adliv);
        $order->setAdFact($adfact);
        $order->setTtc($priceit);
        $order->setTva($vat);
        $order->setPoids($weight);
        $order->setPort(($shippingPrice) ? $shippingPrice : 0);
        $order->setPromo($promo);
        $order->setPaysip($paysip);
        $order->setDatenreg(new \Datetime());
        $order->setMemocmd($memoCmd);

        $em->persist($order);

        $em->flush();


        foreach ($cart->getCartlines() as $cartline) {
            $comprd = new Comprd;

            $codcom = $order->getCodcom();
            $codprd = $cartline->getProduct()->getCodprd();
            $quantity = $cartline->getQuantity();
            $prix = $cartline->getProduct()->getPrix();
            $remise = 0;

            $comprd->setCodcom($codcom);
            $comprd->setCodprd($codprd);
            $comprd->setQuant($quantity);
            $comprd->setPrix($prix);
            $comprd->setRemise($remise);

            $em->persist($comprd);
            $em->flush();
        }
        return $order;
    }

    private function notifyClient(Commande $order, $locale, $user, $withDelay)
    {
        /** @var Tpays $paysliv */
        $paysliv = $this->tpaysRepository->findCountryByCode($order->getAdFact()['Pays']);
        $minliv = $paysliv->getMinliv();
        $maxliv = $paysliv->getMaxliv();
        $adliv = $order->getAdLiv();
        if (empty($adliv['Societe'])) {
            $adLivFormatted = [
                $adliv['Civil'],
                $adliv['Prenom'],
                $adliv['Nom'] . '<br>',
                nl2br($adliv['Adresse']) . '<br>',
                $adliv['CP'],
                $adliv['Ville'] . '<br>',
                $this->getCountryFullname($adliv['Pays'])
            ];
        } else {
            $adLivFormatted = [
                $adliv['Societe'] . '<br>',
                $adliv['Civil'],
                $adliv['Prenom'],
                $adliv['Nom'] . '<br>',
                nl2br($adliv['Adresse']) . '<br>',
                $adliv['CP'],
                $adliv['Ville'] . '<br>',
                $this->getCountryFullname($adliv['Pays'])
            ];
        }
        $adliv = join(' ', $adLivFormatted);

        if ($user->getEmail() === null) {
            $email = $order->getAdLiv()['email'];
        } else {
            $email = $user->getEmail();
        }

        $dataOrder = $this->getDataOrderForNotify($order);
        $this->mailer->send(
            [
                $email
            ],
            $this->translator->trans('order.notify.client.subject'),
            $this->renderView('emails/order-notify-order-'.$locale.'.html.twig', [
                'order' => $order,
                'adliv' => $adliv,
                'minliv' => $minliv,
                'maxliv' => $maxliv,
                'withDelay' => $withDelay,
                'dataOrder' => $dataOrder
                ])
        );

        $this->mailer->send(
            $this->getParameter('email_from_address'),
            $this->translator->trans('order.notify.client.subject'),
            $this->renderView('emails/order-notify-order-ro.html.twig', [
                'order' => $order,
                'user' => $user,
                'minliv' => $minliv,
                'maxliv' => $maxliv
                ])
        );
    }

    private function getDataOrderForNotify(Commande $order)
    {
        $dataOrder = [
            "totalPrice" => $order->getMontant() - $order->getPort(),
            "totalPriceIT" => $order->getMontant() - $order->getPort() + $order->getTva(),
            "product" => [],
            "shippingPriceIT" => $order->getPort(),
            "consumerPriceIT" => floatval($order->getTtc()),
            "consumerPrice" => floatval($order->getMontant()),
            "vat" => floatval($order->getTva())
        ];

        $productRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Produit');
        $orderLines = $this->getDoctrine()->getManager()->getRepository('AppBundle:Comprd')->findBy(['codcom' => $order->getCodcom()]);
        $country = 'FR';
        if (!empty($order->getAdliv()['Pays'])) {
            $country = $order->getAdliv()['Pays'];
        }

        /** @var Comprd $orderLine */
        foreach ($orderLines as $k => $orderLine) {
            $i = 1;
            while ($i <= $orderLine->getQuant()) {
                /** @var Produit $product */
                $product = $productRepository->findOneBy(['codprd' => $orderLine->getCodprd()]);

                /** @var Tax $tax */
                $tax = $this->taxRepository->findTax($product->getTypprd(), $country);
                $productTaxRate = ($tax) ? $tax->getRate() : 0;
                $priceIncludeTaxes = floatval(round($product->getPrix(), 2));
                $priceHT = floatval(round($priceIncludeTaxes / (1 + ($productTaxRate / 100)), 2));

                // R??gle pour les CD
                // Si la TVA vaut 0, le prix HT est le prix TTC
                if ($product->getTypprd() === Produit::TYPE_CD && 0 === $productTaxRate) {
                    $priceHT = floatval($product->getPrix());
                }

                $dataOrder['product'][$k]['productTaxRate'] = $productTaxRate;
                $dataOrder['product'][$k]['price'] = $priceHT;
                $dataOrder['product'][$k]['codprd'] = $product->getCodprd();
                $dataOrder['product'][$k]['quantity'] = $orderLine->getQuant();
                $dataOrder['product'][$k]['name'] = $product->getProduitcourt();
                $dataOrder['product'][$k]['priceIT'] = $priceIncludeTaxes;
                $dataOrder['product'][$k]['vatProduct'] = round($dataOrder['product'][$k]['priceIT'] - $dataOrder['product'][$k]['price'], 2);

                $i++;
            }
        }

        return $dataOrder;
    }

    private function getCountryFullname($codPays)
    {
        $countryName = '';
        if ($codPays !== 'FR') {
            /** @var Tpays $country */
            $country = $this->get('AppBundle\Repository\TpaysRepository')->findCountryByCode($codPays);
            $countryName = $country->getNomPays();
        }

        return $countryName;
    }

    /**
     * @Route("/{_locale}/commande", name="order-fr")
     * @Route("/{_locale}/order", name="order-en")
     * @Route("/{_locale}/bestellen", name="order-de")
     * @Route("/{_locale}/ordine", name="order-it")
     * @Route("/{_locale}/orden", name="order-es")
     */
    public function orderAction(Request $request, CountryService $countryService)
    {
        $cancelReturn = false;

        if ($orderId = $request->get('orderId')) {
            if ($order = $this->commandeRepository->findByRef($orderId)) {
                if ($user = $this->getUser()) {
                    $cancelReturn = $user->getCodcli() === $order->getCodcli();
                }
            }
        }

        $cookies = $request->cookies;
        $countries = $this->tpaysRepository->findAllCountry();
        list($countriesJSON, $preferredChoices) = $countryService->orderCountryListByPreferenceEditions($countries);

        $cartId = $cookies->get('cart');
        $cart = $this->cartRepository->find($cartId);

        if ($cartId === null) {
            return $this->render('order/order-error.html.twig');
        }

        if (empty($cart->getCartlines()->getValues())) {
            return $this->render('order/order-error.html.twig');
        }

        $page = $this->pageService->getContentFromRequest($request);
        if (!$page) {
            throw $this->createNotFoundException($this->translator->trans('global.page-not-found'));
        }
        $availableLocales = $this->pageService->getAvailableLocales($page);
        return $this->render('order/order.html.twig', [
            'cart' => $this->cartRepository->find($cartId),
            'order' => $cancelReturn ? json_encode([
                'adliv' => $order->getAdliv(),
                'codcli' => $order->getCodcli(),
                'datliv' => $order->getDatliv(),
                'destliv' => $order->getDestliv(),
                'memocmd' => $order->getMemocmd(),
                'modliv' => $order->getModliv(),
                'modpaie' => $order->getModpaie(),
                'paysip' => $order->getPaysip(),
                'paysliv' => $order->getAdLiv()['Pays'],
                'validpaie' => $order->getValidpaie()
            ]) : 'false',
            'user' => $cancelReturn ? json_encode([
                'codcli' => $user->getCodcli(),
                'prenom' => $user->getPrenom(),
                'nom' => $user->getNom(),
                'adresse' => $user->getAdresse(),
                'cp' => $user->getCp(),
                'ville' => $user->getVille(),
                'pays' => $user->getPays(),
                'conData' => $user->getConData()
            ]) : 'false',
            'page' => $page,
            'countries' => $countriesJSON,
            'preferredCountries' => $preferredChoices,
            'availableLocales' => $availableLocales,
            'cartCount' => $this->cartService->getCartCount($cookies->get('cart'))
        ]);
    }
}
