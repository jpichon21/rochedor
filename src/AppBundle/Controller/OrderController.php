<?php

/*
 * (c) Logomotion <production@logomotion.fr>
 *
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use AppBundle\Repository\CalendarRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatorInterface as Translator;
use AppBundle\Entity\Contact;
use AppBundle\Entity\ContactL;
use AppBundle\Entity\CalL;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use AppBundle\Entity\Page;
use AppBundle\Entity\Cart;
use AppBundle\Entity\Cartline;
use AppBundle\Entity\Commande;
use AppBundle\Entity\Comprd;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations as Rest;
use AppBundle\Repository\CommandeRepository;
use AppBundle\Repository\CartRepository;
use AppBundle\Service\Mailer;

class OrderController extends Controller
{
    const TVA = 5.5;
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
     * @var CartRepository
     */
    private $cartRepository;

    public function __construct(
        CommandeRepository $commandeRepository,
        CartRepository $cartRepository,
        Mailer $mailer,
        Translator $translator
    ) {
        $this->commandeRepository = $commandeRepository;
        $this->cartRepository = $cartRepository;
        $this->mailer = $mailer;
        $this->translator = $translator;
    }

    /**
     * @Rest\Post("/order/delivery", name="post_delivery")

     * @Rest\View()
    */
    public function xhrPostAddrDeliveryAction(Request $request)
    {
        $cookies = $request->cookies;
        $cartId = $cookies->get('cart');
        $delivery = $request->get('delivery');
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
        if ($this->registerOrder($delivery, $cartId)) {
            return ['status' => 'ok'];
        }
        return ['status' => 'ko', 'message' => 'an error as occured'];
    }

    private function registerOrder($delivery, $cartId)
    {
        // $codcli = $user['codco'];
        $codcli = 37898;
        
        // $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        $user = $this->getDoctrine()->getRepository('AppBundle:Contact')->findByCodco($codcli);
        

        // $cart = $this->getCart($cartId);
        $cart = $this->getCart($delivery['cartId']);
    
        $datCom = new \DateTime();
        $amountHT = $this->getTotalPrice($cart);
        $modpaie = $delivery['modpaie'];
        $modliv = $delivery['modliv'];
        $datpaie = new \DateTime();
        $validpaie = $delivery['validpaie'];
        $destliv = $delivery['destliv'];
        $adliv = $delivery['adliv'];
        $paysliv = $delivery['paysliv'];

        $ttc = $this->getTTCPrice($amountHT);
        $tva = $this->getTVACost($ttc, $amountHT);
        $poids = $this->getTotalWeight($cart);
        $port = 3;
        $promo = 0;

        $datliv = new \Datetime($delivery['datliv']);
        $paysip = $delivery['paysip'];
        $dateenreg = new \Datetime($delivery['dateenreg']);

        $em = $this->getDoctrine()->getManager();
        $order = new Commande;
        
        $order->setRefcom($this->generateRefCom());
        $order->setCodcli($codcli);
        $order->setDatcom($datCom);
        $order->setMontant($amountHT);
        $order->setModpaie($modpaie);
        $order->setModliv($modliv);
        $order->setDatpaie($datpaie);
        $order->setValidpaie($validpaie);
        $order->setDestliv($destliv);
        $order->setAdLiv($adliv);
        $order->setPaysliv($paysliv);
        $order->setTtc($ttc);
        $order->setTva($tva);
        $order->setPoids($poids);
        $order->setPort($port);
        $order->setPromo($promo);
        $order->setDatliv($datliv);
        $order->setPaysip($paysip);
        $order->setDatenreg($dateenreg);
    
        $em->persist($order);
        $em->flush();

        
        foreach ($cart->getCartlines() as $cartline) {
            $comprd = new Comprd;

            $codcom = $order->getCodcom();
            $codprd = $cartline->getProduct()->getCodprd();
            $quantity = $cartline->getQuantity();
            $prix = $cartline->getProduct()->getCodprd();
            $remise = 0;

            $comprd->setCodcom($codcom);
            $comprd->setCodprd($codprd);
            $comprd->setQuant($quantity);
            $comprd->setPrix($prix);
            $comprd->setRemise($remise);

            $em->persist($comprd);
            $em->flush();
        }
        $this->notifyClient($order, $user);
        return $order;
    }
    
    private function getCart($id)
    {
        $cart = $this->cartRepository->findCart($id);
        return $cart;
    }

    private function getTotalWeight($cart)
    {
        $totalWeight = 0;
        foreach ($cart->getCartlines() as $cartline) {
            $totalWeight = $totalWeight + $cartline->getProduct()->getPoids();
        }
        return $totalWeight;
    }

    private function getTotalPrice($cart)
    {
        $totalPrice = 0;
        foreach ($cart->getCartlines() as $cartline) {
            $totalPrice = $totalPrice + $cartline->getProduct()->getPrix() * $cartline->getQuantity();
        }
        return $totalPrice;
    }

    private function getTTCPrice($totalPrice)
    {
        $prixTTC = $totalPrice*(1+($this::TVA/100));
        return $prixTTC;
    }
    
    private function getTVACost($TTCprice, $HTprice)
    {
        $TVA = $TTCprice - $HTprice;
        return $TVA;
    }

    private function notifyClient($order, $user)
    {
        $country = $this->translator->trans('order.country.list', [$order->getPaysliv()]);
        $this->mailer->send(
            // $this->getUser()->getEmail(),
            "mail@mail.com",
            $this->translator->trans('order.notify.client.subject'),
            $this->renderView('emails/order-notify-order.html.twig', [
                'order' => $order,
                'country' => $country,
                'user' => $user
                ])
        );

        $this->mailer->send(
            "secretariat@rochedor.fr",
            $this->translator->trans('order.notify.client.subject'),
            $this->renderView('emails/order-notify-order.html.twig', [
                'order' => $order,
                'country' => $country,
                'user' => $user
                ])
        );
    }

    private function generateRefCom()
    {
        $refcom = $this->commandeRepository->generateRefCom();
        return $refcom;
    }
}
