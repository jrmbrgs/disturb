<?php

namespace Vpg\Disturb\Workflow;

use Vpg\Disturb\Topic;
use Vpg\Disturb\Message;
use Vpg\Disturb\Core;

/**
 * Manager task
 *
 * @package  Disturb\Workflow
 * @author   Jérome BOURGEAIS <jbourgeais@voyageprive.com>
 * @license  https://github.com/vpg/disturb/blob/master/LICENSE MIT Licence
 */
class ManagerWorker extends Core\AbstractWorker
{
    protected $taskOptionList = [
        '?name:'    // optional workflow name
    ];

    /**
     * Todo : improve usage handling
     *
     * @return void
     */
    protected function usage()
    {
        $this->getDI()->get('logger')->debug('Usage : ');
        $this->getDI()->get('logger')->debug(
            'disturb.php "Tasks\\Manager" start --workflow="/path/to/workflow/config/file.json" [--name="workflowName"]'
        );
    }

    /**
     * Init work with parameters
     *
     * @return void
     */
    protected function initWorker()
    {
        $this->getDI()->get('logger')->debug(json_encode(func_get_args()));
        parent::initWorker();
        $serviceFullName = $this->workflowConfig['servicesClassNameSpace'] . "\\" .
            ucFirst($this->workflowConfig['name']) . 'Manager';
        // xxx Allow client to overwrite ?
        $this->workflowManagerService = new WorkflowManager($this->paramHash['workflow']);
        $this->getDI()->get('logger')->debug('Loading ' . $serviceFullName);
        $this->service = new $serviceFullName();

        $this->topicName = Topic\TopicService::getWorkflowManagerTopicName($this->workflowConfig['name']);
    }

    /**
     * Process Dtos message
     *
     * @param Message\MessageDto $messageDto message object
     *
     * @throws WorkflowException
     *
     * @return void
     */
    protected function processMessage(Message\MessageDto $messageDto)
    {
        $this->getDI()->get('logger')->info('messageDto : ' . $messageDto);
        switch ($messageDto->getType()) {
            case Message\MessageDto::TYPE_WF_CTRL:
                switch ($messageDto->getAction()) {
                    case 'start':
                        $this->workflowManagerService->init($messageDto->getId());
                        $this->runNextStep($messageDto->getId());
                    break;
                }
            break;
            case Message\MessageDto::TYPE_STEP_ACK:
                $this->getDI()->get('logger')->debug(
                    "Step {$messageDto->getStepCode()} says {$messageDto->getResult()}"
                );
                $stepResultHash = json_decode($messageDto->getResult(), true);
                $this->workflowManagerService->processStepJobResult(
                    $messageDto->getId(),
                    $messageDto->getStepCode(),
                    $messageDto->getJobId(),
                    $stepResultHash
                );

                $status = $this->workflowManagerService->getStatus($messageDto->getId());
                $this->getDI()->get('logger')->debug("Id {$messageDto->getId()} is '$status'");
                if ($status == WorkflowManager::STATUS_FAILED) {
                    throw new WorkflowException("Id failed {$messageDto->getId()}");
                }

                switch ($this->workflowManagerService->getCurrentStepStatus($messageDto->getId())) {
                    case WorkflowManager::STATUS_RUNNING:
                        // xxx check timeout
                    break;
                    case WorkflowManager::STATUS_SUCCESS:
                        $this->runNextStep($messageDto->getId());
                    break;
                    case WorkflowManager::STATUS_FAILED:
                        $this->workflowManagerService->setStatus(
                            $messageDto->getId(),
                            WorkflowManager::STATUS_FAILED
                        );
                    break;
                    default:
                    throw new WorkflowException('Can\'t retrieve current step status');
                }
            break;
            default:
                $this->getDI()->get('logger')->error("ERR : Unknown message type : {$messageDto->getType()}");
        }
    }

    /**
     * Run next step with previous workflow process id
     *
     * @param string $workflowProcessId workflow process id
     *
     * @return void
     */
    protected function runNextStep(string $workflowProcessId)
    {

        $stepHashList = $this->workflowManagerService->getNextStepList($workflowProcessId);
        if (empty($stepHashList)) {
            $this->getDI()->get('logger')->info("No more step to run, WF ends");
            var_dump($this->workflowManagerService->getContext($workflowProcessId));
        }
        $this->workflowManagerService->initNextStep($workflowProcessId);

        // run through the next step(s)
        foreach ($stepHashList as $stepHash) {
            $stepCode = $stepHash['name'];
            $stepInputList = $this->service->getStepInput($workflowProcessId, $stepCode);
            // run through the "job" to send to each step
            foreach ($stepInputList as $jobId => $stepJobHash) {
                $this->workflowManagerService->registerStepJob($workflowProcessId, $stepCode, $jobId);
                $messageHash = [
                    'id' => $workflowProcessId,
                    'type' => Message\MessageDto::TYPE_STEP_CTRL,
                    'jobId' => $jobId,
                    'stepCode' => $stepCode,
                    'action' => 'start',
                    'payload' => $stepJobHash
                ];
                $stepMessageDto = new Message\MessageDto(json_encode($messageHash));

                $this->sendMessage(
                    Topic\TopicService::getWorkflowStepTopicName($stepCode, $this->workflowConfig['name']),
                    $stepMessageDto
                );
            }
        }
    }
}
