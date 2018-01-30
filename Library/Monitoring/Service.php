<?php
namespace Vpg\Disturb\Monitoring;

use \Phalcon\Config;
use \Phalcon\Mvc\User\Component;

use Vpg\Disturb\Workflow;
use Vpg\Disturb\Core;
use Vpg\Disturb\Core\Storage;

/**
 * Class WorkerService monitoring
 *
 * @package  Disturb\Monitoring
 * @author   Jérôme Bourgeais <jbourgeais@voyageprive.com>
 * @license  https://github.com/vpg/disturb/blob/master/LICENSE MIT Licence
 */
class Service extends Component
{

    /**
     * ContextStorage constructor
     *
     * @param string flow\WorkflowConfigDto $workflowConfigDto config
     *
     * @throws ContextStorageException
     */
    public function __construct(string $workflowConfigFilePath)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $this->config =  Workflow\WorkflowConfigDtoFactory::get($workflowConfigFilePath);
        $this->initClient();
    }

    /**
     * Initialization of Elasticsearch Client
     *
     * @throws ContextStorageException
     *
     * @return void
     */
    private function initClient()
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $this->storageClient = Storage\StorageAdapterFactory::get(
            $this->config,
            Storage\StorageAdapterFactory::USAGE_MONITORING
        );
    }

    /**
     * Registers a worker into the monitoring sys
     *
     * @param string $workerCode the worker's code to register
     *
     * @return void
     */
    public function logWorkerBeat(string $workerCode)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $workerHash = [
            'heartBeatAt' => date('Y-m-d H:i:s')
        ];
        $this->storageClient->save($workerCode, $workerHash);
    }

    /**
     * Registers a worker into the monitoring sys
     *
     * @param string $workerCode the worker's code to register
     * @param int    $pid        the worker's pid
     *
     * @return void
     */
    public function logWorkerStarted(string $workerCode, int $pid)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $workerHash = [
            'status' => Core\Worker\AbstractWorker::STATUS_STARTED,
            'runingOn' => php_uname("n"),
            'pid' => $pid,
            'startedAt' => date('Y-m-d H:i:s'),
            'heartBeatAt' => date('Y-m-d H:i:s')
        ];
        $this->storageClient->save($workerCode, $workerHash);
    }

    /**
     * Registers a worker into the monitoring sys
     *
     * @param string $workerCode the worker's code to register
     * @param int    $exitCode   the worker's exit code
     *
     * @return void
     */
    public function logWorkerExited(string $workerCode, int $exitCode = 0)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $workerHash = [
            'exitedAt' => date('Y-m-d H:i:s'),
            'runingOn' => php_uname("n"),
            'status' => Core\Worker\AbstractWorker::STATUS_EXITED,
            'exitCode' => $exitCode
        ];
        $this->storageClient->save($workerCode, $workerHash);
    }

    /**
     * Gets the worker info
     *
     * @param string $workerCode the worker's code to register
     *
     * @return array the worker info hash
     */
    public function getWorkerInfo(string $workerCode)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        return $this->storageClient->get($workerCode);
    }

    /**
     * Deletes the worker info
     *
     * @param string $workerCode the worker's code to register
     *
     * @return void
     */
    public function deleteWorkerInfo(string $workerCode)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $this->storageClient->delete($workerCode);
    }
}
