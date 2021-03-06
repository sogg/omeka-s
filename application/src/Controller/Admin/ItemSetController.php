<?php
namespace Omeka\Controller\Admin;

use Omeka\Form\ConfirmForm;
use Omeka\Form\ResourceForm;
use Omeka\Form\ResourceBatchUpdateForm;
use Omeka\Job\Dispatcher;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class ItemSetController extends AbstractActionController
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @param Dispatcher $dispatcher
     */
    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function searchAction()
    {
        $view = new ViewModel;
        $view->setVariable('query', $this->params()->fromQuery());
        return $view;
    }

    public function addAction()
    {
        $form = $this->getForm(ResourceForm::class);
        $form->setAttribute('id', 'add-item-set');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $response = $this->api($form)->create('item_sets', $data);
                if ($response) {
                    $message = new Message(
                        'Item set successfully created. %s', // @translate
                        sprintf(
                            '<a href="%s">%s</a>',
                            htmlspecialchars($this->url()->fromRoute(null, [], true)),
                            $this->translate('Add another item set?')
                        ));
                    $message->setEscapeHtml(false);
                    $this->messenger()->addSuccess($message);
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function editAction()
    {
        $form = $this->getForm(ResourceForm::class);
        $form->setAttribute('id', 'edit-item-set');
        $response = $this->api()->read('item_sets', $this->params('id'));
        $itemSet = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('itemSet', $itemSet);
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $response = $this->api($form)->update('item_sets', $this->params('id'), $data);
                if ($response) {
                    $this->messenger()->addSuccess('Item set successfully updated'); // @translate
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $view;
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('item_sets', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $view = new ViewModel;
        $itemSets = $response->getContent();
        $view->setVariable('itemSets', $itemSets);
        $view->setVariable('resources', $itemSets);
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);
        return $view;
    }

    public function showAction()
    {
        $response = $this->api()->read('item_sets', $this->params('id'));

        $view = new ViewModel;
        $itemSet = $response->getContent();
        $view->setVariable('itemSet', $itemSet);
        $view->setVariable('resource', $itemSet);
        return $view;
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('item_sets', $this->params('id'));
        $itemSet = $response->getContent();
        $values = $itemSet->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('resource', $itemSet);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    public function sidebarSelectAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('item_sets', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $view->setVariable('itemSets', $response->getContent());
        $value = $this->params()->fromQuery('value');
        $view->setVariable('searchValue', $value ? $value['in'][0] : '');
        $view->setTerminal(true);
        return $view;
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('item_sets', $this->params('id'));
        $itemSet = $response->getContent();
        $values = $itemSet->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $itemSet);
        $view->setVariable('resourceLabel', 'item set'); // @translate
        $view->setVariable('partialPath', 'omeka/admin/item-set/show-details');
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('item_sets', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Item set successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute(
            'admin/default',
            ['action' => 'browse'],
            true
        );
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one item set to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('item_sets', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Item sets successfully deleted'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function batchDeleteAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        // Derive the query, removing limiting and sorting params.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $job = $this->dispatcher->dispatch('Omeka\Job\BatchDelete', [
                'resource' => 'item_sets',
                'query' => $query,
            ]);
            $this->messenger()->addSuccess('Deleting item sets. This may take a while.'); // @translate
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    /**
     * Batch update selected item sets.
     */
    public function batchEditAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one item set to batch edit.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resources = [];
        foreach ($resourceIds as $resourceId) {
            $resources[] = $this->api()->read('item_sets', $resourceId)->getContent();
        }

        $form = $this->getForm(ResourceBatchUpdateForm::class);
        if ($this->params()->fromPost('batch_update')) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                list($dataRemove, $dataAppend) = $this->preprocessBatchUpdateData($data);

                $this->api($form)->batchUpdate('item_sets', $resourceIds, $dataRemove, [
                    'continueOnError' => true,
                    'collectionAction' => 'remove',
                ]);
                $this->api($form)->batchUpdate('item_sets', $resourceIds, $dataAppend, [
                    'continueOnError' => true,
                    'collectionAction' => 'append',
                ]);

                $this->messenger()->addSuccess('Item sets successfully edited'); // @translate
                return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('resources', $resources);
        $view->setVariable('query', []);
        $view->setVariable('count', null);
        return $view;
    }

    /**
     * Batch update all item sets returned from a query.
     */
    public function batchEditAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        // Derive the query, removing limiting and sorting params.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);
        $count = $this->api()->search('item_sets', ['limit' => 0] + $query)->getTotalResults();

        $form = $this->getForm(ResourceBatchUpdateForm::class);
        if ($this->params()->fromPost('batch_update')) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                list($dataRemove, $dataAppend) = $this->preprocessBatchUpdateData($data);

                $job = $this->dispatcher->dispatch('Omeka\Job\BatchUpdate', [
                    'resource' => 'item_sets',
                    'query' => $query,
                    'data_remove' => $dataRemove,
                    'data_append' => $dataAppend,
                ]);

                $this->messenger()->addSuccess('Editing item sets. This may take a while.'); // @translate
                return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setTemplate('omeka/admin/item-set/batch-edit.phtml');
        $view->setVariable('form', $form);
        $view->setVariable('resources', []);
        $view->setVariable('query', $query);
        $view->setVariable('count', $count);
        return $view;
    }

    /**
     * Preprocess batch update data.
     *
     * Batch update data contains instructions on what to update. It needs to be
     * preprocessed before it's sent to the API.
     *
     * @param array $data
     * @return array An array containing the collectionAction=remove data as the
     * first element and the collectionAction=append data as the second.
     */
    protected function preprocessBatchUpdateData(array $data)
    {
        $dataRemove = [];
        $dataAppend = [];

        // Set the data to change and data to remove.
        if (in_array($data['is_public'], ['0', '1'])) {
            $dataRemove['o:is_public'] = $data['is_public'];
        }
        if (in_array($data['is_open'], ['0', '1'])) {
            $dataRemove['o:is_open'] = $data['is_open'];
        }
        if (-1 == $data['resource_template']) {
            $dataRemove['o:resource_template'] = ['o:id' => null];
        } elseif (is_numeric($data['resource_template'])) {
            $dataRemove['o:resource_template'] = ['o:id' => $data['resource_template']];
        }
        if (-1 == $data['resource_class']) {
            $dataRemove['o:resource_class'] = ['o:id' => null];
        } elseif (is_numeric($data['resource_class'])) {
            $dataRemove['o:resource_class'] = ['o:id' => $data['resource_class']];
        }
        if (isset($data['clear_property_values'])) {
            $dataRemove['clear_property_values'] = $data['clear_property_values'];
        }

        // Set the data to append.
        if (isset($data['value'])) {
            foreach ($data['value'] as $value) {
                $valueObj = [
                    'property_id' => $value['property_id'],
                    'type' => $value['type'],
                ];
                switch ($value['type']) {
                    case 'uri':
                        $valueObj['@id'] = $value['id'];
                        $valueObj['o:label'] = $value['label'];
                        break;
                    case 'resource':
                        $valueObj['value_resource_id'] = $value['value_resource_id'];
                        break;
                    case 'literal':
                    default:
                        $valueObj['@value'] = $value['value'];
                }
                $dataAppend[$value['property_id']][] = $valueObj;
            }
        }

        return [$dataRemove, $dataAppend];
    }
}
