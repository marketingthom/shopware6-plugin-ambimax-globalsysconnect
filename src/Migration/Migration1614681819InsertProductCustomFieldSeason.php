<?php declare(strict_types=1);

namespace Ambimax\GlobalsysConnect\Migration;

use Ambimax\GlobalsysConnect\AmbimaxGlobalsysConnect;
use Ambimax\GlobalsysConnect\Migration\Exception\CustomFieldSetIdNotFoundException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1614681819InsertProductCustomFieldSeason extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1614681819;
    }

    /**
     * @param Connection $connection
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $setName = AmbimaxGlobalsysConnect::CUSTOM_FIELD_SET_NAMES['product'];
        $setId = $this->queryCustomFieldSetId($setName, $connection);

        if (!$setId) {
            throw new CustomFieldSetIdNotFoundException('No field set found with name ' . $setName);
        }

        $customFieldName = AmbimaxGlobalsysConnect::CUSTOM_FIELD_PRODUCT_SEASON;
        $customFieldId = strtoupper(Uuid::randomHex());
        $config = $this->getConfigJsonString();

        $query = <<<SQL
            INSERT INTO `custom_field`
                (`id`, `name`, `type`, `config`, `active`, `set_id`, `created_at`, `updated_at`)
                VALUES
                (UNHEX(:customFieldId), :customFieldName, 'text', :config, 1, UNHEX(:setId), NOW(), NULL);
SQL;
        $connection->executeStatement(
            $query,
            [
                'customFieldId'   => $customFieldId,
                'customFieldName' => $customFieldName,
                'config'          => $config,
                'setId'           => $setId
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @param string $setName
     * @param Connection $connection
     * @return array|false|mixed|string
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    protected function queryCustomFieldSetId(string $setName, Connection $connection)
    {
        $query = <<<SQL
                SELECT HEX(`id`) as 'setId' FROM custom_field_set WHERE `name`=:setName;
SQL;

        $result = $connection->executeQuery($query, ['setName' => $setName]);
        return $result->fetchOne();
    }

    /**
     * @return string
     */
    protected function getConfigJsonString(): string
    {
        return json_encode([
            'label'      => [
                'en-GB' => "Season",
                'de-DE' => "Saison"
            ],
            'helpText'   => [
                'en-GB' => "Describes to which season this product belongs",
                'de-DE' => "Beschreibt zu welcher Saison dieses Produkt gehört",
            ],
            'translated' => true
        ], JSON_UNESCAPED_UNICODE);
    }
}
