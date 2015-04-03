<?php
namespace Omeka\Media\Handler;

use Omeka\Api\Request;
use Omeka\Media\Handler\AbstractFileHandler;
use Omeka\Model\Entity\Media;
use Omeka\Stdlib\ErrorStore;
use Zend\Http\Exception\ExceptionInterface as HttpExceptionInterface;
use Zend\Uri\Http as HttpUri;
use Zend\View\Renderer\PhpRenderer;

class UrlHandler extends AbstractFileHandler
{
    /**
     * {@inheritDoc}
     */
    public function validateRequest(Request $request, ErrorStore $errorStore)
    {}

    /**
     * {@inheritDoc}
     */
    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (!isset($data['ingest_url'])) {
            $errorStore->addError('error', 'No ingest URL specified');
            return;
        }

        $uri = new HttpUri($data['ingest_url']);
        if (!($uri->isValid() && $uri->isAbsolute())) {
            $errorStore->addError('ingest_url', 'Invalid ingest URL');
            return;
        }

        $file = $this->getServiceLocator()->get('Omeka\File');
        $file->setSourceName($uri->getPath());
        $this->downloadFile($uri, $file->getTempPath());

        $fileManager = $this->getServiceLocator()->get('Omeka\File\Manager');
        $hasThumbnails = $fileManager->storeThumbnails($file);
        $media->setHasThumbnails($hasThumbnails);

        if (!isset($data['store_original']) || $data['store_original']) {
            $fileManager->storeOriginal($file);
            $media->setHasOriginal(true);
        }

        $media->setFilename($file->getStorageName());
        $media->setMediaType($file->getMediaType());

        if (!array_key_exists('o:source', $data)) {
            $media->setSource($uri);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function form(PhpRenderer $view, array $options = array())
    {}
}
