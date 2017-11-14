<?php

/**
 * StepTask
 *
 * @category Tasks
 * @package  Disturb\Tasks
 * @author   Jérome BOURGEAIS <jbourgeais@voyageprive.com>
 * @license  https://github.com/vpg/disturb/blob/poc/LICENSE MIT Licence
 * @version  0.1.0
 * @link     http://example.com/my/bar Documentation of Foo.
 */

namespace Vpg\Disturb\Tasks;

use \Phalcon\Cli\Task;

use \Vpg\Disturb\Dtos;
use \Vpg\Disturb\Services;
use \Vpg\Disturb\Tasks\AbstractTask as AbstractTask;

/**
 * Generic Step task
 * Dedicated to one step, given in argv with --step argument
 *
 * @category Tasks
 * @package  Disturb\Tasks
 * @author   Jérome BOURGEAIS <jbourgeais@voyageprive.com>
 * @license  https://github.com/vpg/disturb/blob/poc/LICENSE MIT Licence
 * @version  0.1.0
 * @link     http://example.com/my/bar Documentation of Foo.
 * @see      \Disturb\Tasks\AbstractTask
 */
class StepTask extends AbstractTask
{

    protected $taskOptionList = [
        'step:',     // required step code config file
    ];

    /**
     * Todo : improve usage handling
     *
     * @return void
     */
    protected function usage()
    {
        $this->getDI()->get('logger')->debug('Usage : ');
        $this->getDI()->get('logger')->debug('disturb.php "Tasks\\Step" start --step="stepName" --workflow="/path/to/workflow/config/file.json" [--name="workflowName"]');
    }

    /**
     * Uses the business service related to the current step to process the given message
     *  - The message processing is fully delegated to the "client" service implementing the
     * \Disturb\Services\StepServiceInterface.php by calling the execute method
     *  - the process result (returned by the service) is sent back to the manager
     *
     * @param Vpg\Disturb\Dtos\Message $messageDto the message to process
     *
     * @return void
     */
    protected function processMessage(Dtos\Message $messageDto)
    {
        $this->getDI()->get('logger')->info('messageDto : ' . $messageDto);
        $resultHash = $this->service->execute($messageDto->getPayload());
        $msgDto = new Dtos\Message(
            [
                'id' => $messageDto->getId(),
                'type' => Dtos\Message::TYPE_STEP_ACK,
                'stepCode' => $messageDto->getStepCode(),
                'jobId' => $messageDto->getJobId(),
                'result' => json_encode($resultHash)
            ]
        );

        $this->sendMessage(
            Services\TopicService::getWorkflowManagerTopicName($this->workflowConfig['name']),
            $msgDto
        );
    }

    /**
     * Specializes the current Step according to the given argvs
     *  - Sets the topic
     *  - Instanciates the "Client" service
     *
     * @param array $paramHash the parsed step task argv
     *
     * @return void
     */
    protected function initWorker(array $paramHash)
    {
        $this->getDI()->get('logger')->debug(json_encode(func_get_args()));
        parent::initWorker($paramHash);
        $serviceFullName = $this->workflowConfig['servicesClassNameSpace'] . '\\' .
            ucFirst($paramHash['step']) . 'Step';
        $this->service = new $serviceFullName($paramHash['workflow']);

        $this->topicName = Services\TopicService::getWorkflowStepTopicName($paramHash['step'], $this->workflowConfig['name']);
    }
}
