<?php

declare(strict_types=1);

namespace PartialDelivery;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class PartialDelivery extends Plugin
{
    /**
     * The return-management integration (OrderReturnRouteDecorated) decorates a
     * Shopware Commercial service, so Commercial must be installed and active.
     */
    private const COMMERCIAL_RETURN_ROUTE = 'Shopware\\Commercial\\ReturnManagement\\Domain\\Returning\\OrderReturnRoute';

    public function install(InstallContext $installContext): void
    {
        // Do stuff such as creating a new payment method
    }

    public function activate(ActivateContext $activateContext): void
    {
        if (!class_exists(self::COMMERCIAL_RETURN_ROUTE)) {
            throw new \RuntimeException(
                'The Partial Delivery plugin requires Shopware Commercial (Return Management) '
                . 'to be installed and active.'
            );
        }
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
    }

    public function update(UpdateContext $updateContext): void
    {
    }

    public function postInstall(InstallContext $installContext): void
    {
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
    }
}
