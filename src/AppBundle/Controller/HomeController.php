<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Page;
use FOS\RestBundle\Controller\Annotations\Get;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Gedmo\Loggable;
use Symfony\Cmf\Bundle\RoutingBundle\Doctrine\Orm\Route as CmfRoute;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use AppBundle\ServiceShowPage;

class HomeController extends Controller
{
    
    /**
    * @Rest\Get("/home/{locale}/{version}", requirements={"version"="\d+"} , defaults={"version" = null})")
    * @Rest\View()
    */
    public function showAction($locale, $version, ServiceShowPage $pageService)
    {
        $page = $pageService->getMyContent($locale);
        if (!$page) {
            return new JsonResponse(['message' => 'Page not found'], Response::HTTP_NOT_FOUND);
        }
        
        if ($version === null) {
            if ($page->getRoutes()) {
                $page->setTempUrl($page->getRoutes()[0]->getName());
            }
            return $page;
        }
        
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('Gedmo\Loggable\Entity\LogEntry'); // we use default log entry class
        $logs = $repo->getLogEntries($page);
        $countLogs = count($logs) - 1;
        $firstLog = $logs[$countLogs];
        for ($i = ($countLogs); $i >= 0; $i--) {
            if ($logs[$i]->getVersion() <= $version) {
                $diff = array_diff_key($firstLog->getData(), $logs[$i]->getData());
                $oldPage = array_merge($diff, $logs[$i]->getData());
            }
        }
        $page->setTitle($oldPage['title']);
        $page->setSubTitle($oldPage['subTitle']);
        $page->setDescription($oldPage['description']);
        $page->setContent($oldPage['content']);
        if ($page->getRoutes()) {
            $page->setTempUrl($page->getRoutes()[0]->getName());
        }
        return $page;
    }
    
    
    /**
    * @Rest\Put("/home/{id}/")
    * @Rest\View()
    */
    public function putAction($id, Request $request)
    {
        $data = new Page;
        $title = $request->get('title');
        $subTitle = $request->get('sub_title');
        $description = $request->get('description');
        $content = $request->get('content');
        $bg = $request->get('background');
        $url = $request->get('url');
        $locale = $request->get('locale');
        $em = $this->getDoctrine()->getManager();
        $page = $em->find('AppBundle\Entity\Page', $id);
        $gedmo = $em->getRepository('Gedmo\Loggable\Entity\LogEntry');
        $logs = $gedmo->getLogEntries($page);
        if (empty($page)) {
            return new JsonResponse(['message' => 'Page not found'], Response::HTTP_NOT_FOUND);
        } else {
            $page->setTitle($title);
            $page->setSubTitle($subTitle);
            $page->setDescription($description);
            $page->setContent($content);
            $page->setBackground($bg);
            $page->setLocale($locale);
            
            $oldUrl = null;
            if ($page->getRoutes()) {
                $oldUrl = $page->getRoutes()[0]->getName();
            }
            
            if ($oldUrl !== $url) {
                $routeProvider = $this->container->get('cmf_routing.route_provider');
                if ($routeProvider->getRoutesByNames([$url])) {
                    return new JsonResponse(['message' => 'Route already exists'], Response::HTTP_FORBIDDEN);
                }
                
                $routes = $page->getRoutes();
                foreach ($routes as $key => $route) {
                    $route->setName($url);
                    $route->setStaticPrefix('/' . $url);
                    $routes[$key] = $route;
                }
            }
            
            
            $em->persist($page);
            $em->flush();
            
            return new JsonResponse(['message' => 'Page Updated'], Response::HTTP_OK);
        }
    }
    
    /**
    * @Rest\Get("home/{id}/versions")
    * @Rest\View()
    *
    * @param integer $id
    * @return json
    */
    public function getVersionsAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('Gedmo\Loggable\Entity\LogEntry'); // we use default log entry class
        $page = $em->getRepository('AppBundle:Page')->findOneById($id);
        if (empty($page)) {
            return new JsonResponse(['message' => 'Page not found'], Response::HTTP_NOT_FOUND);
        }
        $logs = $repo->getLogEntries($page);
        return $logs;
    }
}