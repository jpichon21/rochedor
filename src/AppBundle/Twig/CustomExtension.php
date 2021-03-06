<?php
namespace AppBundle\Twig;
 
use \Symfony\Cmf\Bundle\RoutingBundle\Doctrine\Orm\RouteProvider;
use \AppBundle\Service\PageService;
use Symfony\Component\Translation\TranslatorInterface;

class CustomExtension extends \Twig_Extension
{
    /**
     * @var RouteProvider
     */
    private $routeProvider;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var PageService
     */
    private $pageService;
    public function __construct(RouteProvider $routeProvider, PageService $pageService, TranslatorInterface $translator)
    {
        $this->routeProvider = $routeProvider;
        $this->pageService = $pageService;
        $this->translator = $translator;
    }
 
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('position', array($this, 'positionFilter')),
            new \Twig_SimpleFilter('background', array($this, 'backgroundFilter')),
            new \Twig_SimpleFilter('constantBackground', array($this, 'constantBackgroundFilter')),
            new \Twig_SimpleFilter('md5', array($this, 'md5Filter')),
            new \Twig_SimpleFilter('slugify', array($this, 'slugifyFilter')),
        );
    }
 
    public function getFunctions()
    {
        return array(
            new \Twig_Function('routeExists', array($this, 'routeExists')),
            new \Twig_Function('localePath', array($this, 'localePath')),
            new \Twig_Function('loadTranslations', array($this, 'loadTranslations')),
        );
    }
 
    public function positionFilter($position)
    {
        return "background-position:$position;";
    }
 
    public function backgroundFilter($url)
    {
        return "background-image:url($url);";
    }
 
    public function constantBackgroundFilter($id)
    {
        $url = constant('AppBundle\\Entity\\Page::BACKGROUNDS')[$id];
        return "background-image:url($url);";
    }
 
    public function md5Filter($string)
    {
        return md5($string);
    }

    public function slugifyFilter($string, $replace = array(), $delimiter = '-')
    {
        if (!extension_loaded('iconv')) {
            throw new Exception('iconv module not loaded');
        }
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        if (!empty($replace)) {
            $clean = str_replace((array) $replace, ' ', $clean);
        }
        $clean = preg_replace("/[^a-zA-Z0-9_|+ -]/", '', $clean);
        $clean = strtolower($clean);
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
        $clean = trim($clean, $delimiter);
        return $clean;
    }
 
    public function routeExists($routeName)
    {
        return $this->routeProvider->getRoutesByNames([$routeName]);
    }
 
    public function localePath($routeName, $locale)
    {
        $route = $this->routeExists($routeName);
        if (!$route) {
            return '#';
        }
        if ($locale === 'fr') {
            return $route[0]->getStaticPrefix();
        }
        $availableLocales = $this->pageService->getAvailableLocales($this->pageService->getContent($routeName));
        foreach ($availableLocales as $path) {
            if (in_array($locale, explode('/', $path))) {
                return $path;
            }
        }
        return '#';
    }

    public function loadTranslations($locale)
    {
        $domains = $this->translator->getCatalogue($locale);
        $messages = [];
        foreach ($domains->getDomains() as $domain) {
            foreach ($domains->all($domain) as $key => $message) {
                $messages[$key] = $message;
            }
        }
        return json_encode($messages);
    }

    public function getName()
    {
        return 'custom_extension';
    }
}
