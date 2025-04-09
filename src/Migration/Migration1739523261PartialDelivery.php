<?php declare(strict_types=1);

namespace PartialDelivery\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1739523261PartialDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1739523261;
    }

    public function update(Connection $connection): void
    {
        $connection->exec("
        CREATE TABLE IF NOT EXISTS partial_delivery (
            id BINARY(16) NOT NULL,
            order_line_item_id VARCHAR(255) NOT NULL,
            quantity INT NOT NULL,
            package VARCHAR(255) NOT NULL,
            tracking_code VARCHAR(255) NOT NULL,
            created_at DATETIME(3) NOT NULL,
            updated_at DATETIME(3) NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    }

}
