<?php

namespace Oro\Bundle\SecurityBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Oro\Bundle\SecurityBundle\Form\Model\Share;

/**
 * @Route("/share")
 */
class ShareController extends Controller
{
    /**
     * @Route("/update/{entityClass}/{entityId}", name="oro_share_update")
     * @Template("OroSecurityBundle:Share:update.html.twig")
     */
    public function updateAction($entityClass, $entityId)
    {
        $entityRoutingHelper = $this->get('oro_entity.routing_helper');
        $entity = $entityRoutingHelper->getEntity($entityClass, $entityId);
        if (!$this->get('oro_security.security_facade')->isGranted('SHARE', $entity)) {
            throw new AccessDeniedException();
        }

        $formAction = $entityRoutingHelper->generateUrlByRequest(
            'oro_share_update',
            $this->getRequest(),
            $entityRoutingHelper->getRouteParameters($entityClass, $entityId)
        );

        return $this->update($this->get('oro_security.form.model.factory')->getShare(), $entity, $formAction);
    }

    /**
     * @Route("/entities/{entityClass}", name="oro_share_entities")
     * @Template("OroSecurityBundle:Share:entities.html.twig")
     */
    public function entitiesAction($entityClass)
    {
        $supportedGridsInfo = $this->get('oro_security.provider.share_grid_provider')
            ->getSupportedGridsInfo($entityClass);
        $gridEntityClass = '';
        if (isset($supportedGridsInfo[0]['className'])) {
            $gridEntityClass = $supportedGridsInfo[0]['className'];
        }

        return [
            'entityClass' => $gridEntityClass,
            'supportedGridsInfo' => $supportedGridsInfo,
            'params' => [
                'grid_path' => $this->generateUrl(
                    'oro_share_entities_grid',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
            ],
        ];
    }

    /**
     * @Route("/entities/grid/{entityClass}", name="oro_share_entities_grid")
     * @Template("OroDataGridBundle:Grid:dialog/widget.html.twig")
     */
    public function entitiesGridAction($entityClass = null)
    {
        return [
            'params' => [],
            'renderParams' => [],
            'multiselect' => true,
            'gridName' => $this->get('oro_security.provider.share_grid_provider')->getGridName($entityClass),
        ];
    }

    /**
     * @param Share $model
     * @param object $entity
     * @param string $formAction
     *
     * @return array
     */
    protected function update(Share $model, $entity, $formAction)
    {
        $responseData = [
            'entity' => $entity,
            'model' => $model,
            'saved' => false
        ];

        if ($this->get('oro_security.form.handler.share')->process($model, $entity)) {
            $responseData['saved'] = true;
        }
        $responseData['form'] = $this->get('oro_security.form.share')->createView();
        $responseData['formAction'] = $formAction;

        return $responseData;
    }
}
