<?php
namespace Omeka\Controller\Site;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class MediaController extends AbstractActionController
{
    public function showAction()
    {
        $site = $this->getSite();
        $response = $this->api()->read('media', $this->params('id'));
        $item = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('media', $item);
        $view->setVariable('resource', $item);
        return $view;
    }

    protected function getSite()
    {
        return $this->api()->read('sites', array(
            'slug' => $this->params('site-slug')
        ))->getContent();
    }
}
