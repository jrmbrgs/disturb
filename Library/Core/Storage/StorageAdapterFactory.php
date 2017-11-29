<?php
namespace Vpg\Disturb\Core\Storage;

use \Phalcon\DI;

use Vpg\Disturb\Workflow;

/**
 * Class StorageAdapterFactory
 * provide a storage adapater instance according to the given conf
 *
 * @package  Disturb\Core\Storage
 * @author   Jérôme BOURGEAIS <jbourgeais@voyageprive.com>
 * @license  https://github.com/vpg/disturb/blob/master/LICENSE MIT Licence
 */
class StorageAdapterFactory
{
    /**
     * Elastic search adapter
     *
     * @const string ADAPTER_ELASTICSEARCH
     */
    const ADAPTER_ELASTICSEARCH = 'elasticsearch';

    /**
     * Context usage const
     *
     * @const string USAGE_CONTEXT
     */
    const USAGE_CONTEXT = 'context';

    /**
     * Monitoring usage const
     *
     * @const string USAGE_MONITORING
     */
    const USAGE_MONITORING = 'monitoring';


    /**
     * ContextStorage constructor
     *
     * @param Workflow\WorkflowConfigDto $workflowConfigDto config
     * @param string                     $usage             define the usage, could either be context or monitoring
     *
     * @throws StorageException
     *
     * @return StorageAdapterInterface implementation
     */
    public static function get(Workflow\WorkflowConfigDto $workflowConfigDto, string $usage)
    {
        DI::getDefault()->get('logr')->debug(json_encode(func_get_args()));
        // check adapter type
        if (empty($workflowConfigDto->getStorageAdapter())) {
            throw new StorageException(
                'Adapter name not found',
                StorageException::CODE_ADAPTER
            );
        }

        // check if adapter class exists
        switch ($workflowConfigDto->getStorageAdapter()) {
            case self::ADAPTER_ELASTICSEARCH:
                $adapterClass = 'Vpg\\Disturb\\Core\\Storage\\ElasticsearchAdapter';
            break;
            default:
            throw new StorageException(
                'Adapter class not found',
                StorageException::CODE_ADAPTER
            );
        }

        if (! class_exists($adapterClass)) {
            throw new StorageException(
                'Adapter class not found : ' . $adapterClass,
                StorageException::CODE_ADAPTER
            );
        }

        // check if adapter config exists
        if (empty($workflowConfigDto->getStorageConfig())) {
            throw new StorageException(
                'Adapter config not found',
                StorageException::CODE_ADAPTER
            );
        }

        $adapter = new $adapterClass();
        $adapter->initialize(new \Phalcon\Config($workflowConfigDto->getStorageConfig()), $usage);
        return $adapter;
    }
}
