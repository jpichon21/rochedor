<?php

/*
 * (c) Logomotion <production@logomotion.fr>
 *
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;
use AppBundle\Controller\PageController;
use AppBundle\Controller\CalendarController;
use AppBundle\Entity\Page;
use AppBundle\Entity\News;
use AppBundle\Entity\Speaker;
use AppBundle\Service\PageService;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Security\Core\User\UserInterface;

class DefaultController extends Controller
{
    /**
     * @var PageService
     */
    private $pageService;

    public function __construct(PageService $pageService)
    {
        $this->pageService = $pageService;
    }
     /**
     * @Route("/", name="home", defaults={"_locale"="fr"}, requirements={"_locale" = "%locales%"})
     * @Route("/{_locale}/", name="home_locale", requirements={"_locale" = "%locales%"})
     */
    public function indexAction(Request $request)
    {
        $locale = $this->get('translator')->getLocale();
        $contentDocument = $this->pageService->getContent($locale);
        $news = $this->getDoctrine()->getRepository('AppBundle:News')->findForHomepage($contentDocument->getLocale());
        return $this->render('default/index.html.twig', array(
            'page' => $contentDocument,
            'availableLocales' => $this->pageService->getAvailableLocales($contentDocument),
            'news' => $news
        ));
    }

    /**
     * @Route("/{_locale}/intervenants", name="speakers-fr")
     * @Route("/{_locale}/speakers", name="speakers-en")
     * @Route("/{_locale}/lautsprecher", name="speakers-de")
     * @Route("/{_locale}/altoparlanti", name="speakers-it")
     * @Route("/{_locale}/altavoces", name="speakers-es")
     */
    public function showSpeakerAction(Request $request)
    {
        $path = $request->getPathInfo();
        $name = substr($path, 1);
        $page = $this->pageService->getContentFromRequest($request);
        if (!$page) {
            throw $this->createNotFoundException($this->translator->trans('global.page-not-found'));
        }
        $availableLocales = $this->pageService->getAvailableLocales($page);
        $speakers = $this->getDoctrine()->getRepository('AppBundle:Speaker')->findAllOrderByPos();
        foreach ($speakers as $speaker) {
            $localSpeaker = new Speaker;
            $localeTitle = $speaker->getTitle()[$page->getLocale()];
            $localeDesc = $speaker->getDescription()[$page->getLocale()];
            $localSpeaker->setName($speaker->getName());
            $localSpeaker->setTitle($localeTitle);
            $localSpeaker->setDescription($localeDesc);
            $localSpeaker->setImage($speaker->getImage());
            $localSpeaker->setPosition($speaker->getPosition());
            $speakers[] = $localSpeaker;
            unset($speakers[array_search($speaker, $speakers)]);
        }
        return $this->render('default/speaker.html.twig', array(
            'page' => $page,
            'availableLocales' => $availableLocales,
            'speakers'=> $speakers
        ));
    }

    /**
     * @Route("/admin", name="admin")
     */
    public function adminAction()
    {
        return $this->render('admin/index.html.twig');
    }

    public function showPageAction($contentDocument)
    {
        return $this->render('default/page.html.twig', array(
            'page' => $contentDocument,
            'availableLocales' => $this->pageService->getAvailableLocales($contentDocument)
        ));
    }

    /**
     * @Rest\Get("/xhr/translations/{locale}", name="get_translations")
     * @Rest\View()
     */
    public function translationsAction(TranslatorInterface $translator, $locale = null)
    {
        $domains = $translator->getCatalogue($locale);
        $messages = [];
        foreach ($domains->getDomains() as $domain) {
            foreach ($domains->all($domain) as $key => $message) {
                $messages[$key] = $message;
            }
        }
        return $messages;
    }
    
    /**
     * @Route("/{locale}/getterms", name="getcgv")
     */
    public function getTermsAction()
    {
        $user = $this->getUser();
        if ($user->getProfessionnel() === true) {
            return $this->redirectToRoute('cgv-pro');
        }
        return $this->redirectToRoute('cgv');
    }
}
