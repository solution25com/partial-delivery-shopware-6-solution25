<?php

declare(strict_types=1);

namespace PartialDelivery\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;


#[Package('core')]
class Migration1782604800PartialDeliveryIntegrationFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782604800;
    }

    public function update(Connection $connection): void
    {
        $columns = $this->getExistingColumns($connection);

        $additions = [
            'order_id' => 'ADD COLUMN `order_id` BINARY(16) NULL',
            'external_reference' => 'ADD COLUMN `external_reference` VARCHAR(255) NULL',
            'source' => 'ADD COLUMN `source` VARCHAR(64) NULL',
            'payment_status' => 'ADD COLUMN `payment_status` VARCHAR(32) NULL',
            'payment_reference' => 'ADD COLUMN `payment_reference` VARCHAR(255) NULL',
            'amount' => 'ADD COLUMN `amount` DECIMAL(12, 4) NULL',
        ];

        $parts = [];
        foreach ($additions as $column => $sql) {
            if (!\in_array($column, $columns, true)) {
                $parts[] = $sql;
            }
        }

        if ($parts !== []) {
            $connection->executeStatement(
                'ALTER TABLE `partial_delivery` ' . implode(', ', $parts) . ';'
            );
        }

        $indexes = $connection->fetchFirstColumn(
            "SHOW INDEX FROM `partial_delivery` WHERE Key_name = 'idx.partial_delivery.order_id'"
        );

        if ($indexes === []) {
            $connection->executeStatement(
                'ALTER TABLE `partial_delivery` ADD INDEX `idx.partial_delivery.order_id` (`order_id`);'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @return array<int, string>
     */
    private function getExistingColumns(Connection $connection): array
    {
        return array_map(
            static fn (array $row) => $row['Field'],
            $connection->fetchAllAssociative('SHOW COLUMNS FROM `partial_delivery`')
        );
    }
}
