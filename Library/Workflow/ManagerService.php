<?PHP
namespace Vpg\Disturb\Workflow;

use \Phalcon\Config\Adapter\Json;
use \Phalcon\Mvc\User\Component;

use Vpg\Disturb\Context\ContextStorageService;

/**
 * Class WorkflowManager
 *
 * @package  Disturb\Workflow
 * @author   Jérome BOURGEAIS <jbourgeais@voyageprive.com>
 * @license  https://github.com/vpg/disturb/blob/master/LICENSE MIT Licence
 */
class ManagerService extends Component implements WorkflowManagerInterface
{
    /**
     * Status no started const
     *
     * @const string STATUS_NO_STARTED
     */
    const STATUS_NO_STARTED = 'NOT_STARTED';

    /**
     * Status paused const
     *
     * @const string STATUS_PAUSED
     */
    const STATUS_PAUSED = 'PAUSED';

    /**
     * Status started const
     *
     * @const string STATUS_STARTED
     */
    const STATUS_STARTED = 'STARTED';

    /**
     * Status success const
     *
     * @const string STATUS_SUCCESS
     */
    const STATUS_SUCCESS = 'SUCCESS';

    /**
     * Status failed const
     *
     * @const string STATUS_FAILED
     */
    const STATUS_FAILED = 'FAILED';

    /**
     * Status finished const
     *
     * @const string STATUS_FINISHED
     */
    const STATUS_FINISHED = 'FINISHED';

    /**
     * Status running const
     *
     * @const string STATUS_RUNNING
     */
    const STATUS_RUNNING = 'RUNNING';

    /**
     * WorkflowManager constructor.
     *
     * @param string $workflowConfigFilePath config file path
     */
    public function __construct(string $workflowConfigFilePath)
    {
        $this->di->get('logr')->debug("Loading WF from '$workflowConfigFilePath'");

        $this->di->setShared(
            'WorkflowConfig',
            new Json($workflowConfigFilePath)
        );

        $this->di->setShared(
            'contextStorage',
            new ContextStorageService($workflowConfigFilePath)
        );
    }

    /**
     * Init
     *
     * @param string $workflowProcessId the workflow process identifier
     * @param array  $payloadHash       the workflow initial payload
     *
     * @return void
     */
    public function init(string $workflowProcessId, array $payloadHash)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        if ($this->di->get('contextStorage')->exists($workflowProcessId)) {
            throw new WorkflowException("Failed to init workflow '$workflowProcessId' : existing context");
        }
        $this->di->get('contextStorage')->save(
            $workflowProcessId,
            [
                'steps' => $this->di->get('WorkflowConfig')['steps']->toArray(),
                'initialPayload' => $payloadHash,
                'status' => self::STATUS_STARTED,
                'currentStepPos' => -1,
                'startedAt' => date(ContextStorageService::DATE_FORMAT)
                ]
        );
    }

    /**
     * Finalize the given workflow
     *
     * @param string $workflowProcessId the workflow process identifier
     * @param string $workflowStatus    the workflow status
     * @param string $workflowInfo      the workflow info
     *
     * @return void
     */
    public function finalize(string $workflowProcessId, string $workflowStatus, string $workflowInfo = '')
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        if (!$this->di->get('contextStorage')->exists($workflowProcessId)) {
            throw new WorkflowException("Failed to finaliz workflow '$workflowProcessId' : non existing context");
        }
        $this->di->get('contextStorage')->save(
            $workflowProcessId,
            [
                'status'     => $workflowStatus,
                'finishedAt' => date(ContextStorageService::DATE_FORMAT),
                'info'       => $workflowInfo
            ]
        );
    }

    /**
     * Returns the context of the given workflow
     *
     * @param string $workflowProcessId the workflow process identifier
     *
     * @return Context\ContextDto
     */
    public function getContext(string $workflowProcessId)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        return $this->di->get('contextStorage')->get($workflowProcessId);
    }

    /**
     * Set workflow status
     *
     * @param string $workflowProcessId the wf process identifier
     * @param string $status            wf status
     * @param string $message           error message
     *
     * @return void
     */
    public function setStatus(string $workflowProcessId, string $status, string $message = '')
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $this->di->get('logr')->info("Workflow $workflowProcessId is now $status");
        $this->di->get('contextStorage')->setWorkflowStatus($workflowProcessId, $status, $message);
    }

    /**
     * Get workflow status
     *
     * @param string $workflowProcessId the workflow process identifier
     *
     * @return string
     */
    public function getStatus(string $workflowProcessId) : string
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $contextDto = $this->di->get('contextStorage')->get($workflowProcessId);
        return $contextDto->getWorkflowStatus();
    }

    /**
     * Go to next step
     *
     * @param string $workflowProcessId the workflow process identifier
     *
     * @return void
     */
    public function initNextStep(string $workflowProcessId)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $this->di->get('contextStorage')->initWorkflowNextStep($workflowProcessId);
    }

    /**
     * Get next step if it exists
     *
     * @param string $workflowProcessId the wf process identifier
     *
     * @return array
     */
    public function getNextStepList(string $workflowProcessId) : array
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $contextDto = $this->di->get('contextStorage')->get($workflowProcessId);
        $nextStepPos = $contextDto->getWorkflowCurrentPosition() + 1;

        // Manage case when there is no more step to run
        if (empty($this->di->get('WorkflowConfig')->steps[$nextStepPos])) {
            $this->setStatus($workflowProcessId, self::STATUS_FINISHED);
            return [];
        }

        $stepNode = $this->di->get('WorkflowConfig')->steps[$nextStepPos]->toArray();
        if (!$this->isStepParallelized($stepNode)) {
            return [$stepNode];
        }
        return $stepNode;
    }

    /**
     * Returns true if the given workflow has still step to run
     *
     * @param string $workflowProcessId the wf process identifier
     *
     * @return bool
     */
    public function hasNextStep(string $workflowProcessId) : bool
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $contextDto = $this->di->get('contextStorage')->get($workflowProcessId);
        $nextStepPos = $contextDto->getWorkflowCurrentPosition() + 1;
        return !empty($this->di->get('WorkflowConfig')->steps[$nextStepPos]);
    }


    /**
     * Check for a step if all related jobs have been succeed
     *
     * @param array $step context step hash
     *
     * @return string
     */
    private function getStepStatusByJobStatus(array $step) : string
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));

        $jobStatusList = [];
        $jobList = $step['jobList'];

        foreach ($jobList as $job) {
            array_push($jobStatusList, $job['status']);
        }

        return $this->aggregateStatus($jobStatusList);
    }

    /**
     * Get global status of a step / job
     *
     * @param array $statusList list of all step / job statuses
     *
     * @return string
     */
    private function aggregateStatus(array $statusList) : string
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $nbJobs = sizeof($statusList);
        $statusValueList = array_count_values($statusList);
        $status = self::STATUS_FAILED;
        // When all steps / jobs have the same status, return it
        if (in_array($nbJobs, $statusValueList)) {
            $status = $statusList[0];
        } else {
            // If there is one running
            if (
                isset($statusValueList[self::STATUS_NO_STARTED]) ||
                isset($statusValueList[self::STATUS_RUNNING]) ||
                isset($statusValueList[self::STATUS_STARTED])
            ) {
                $status = self::STATUS_RUNNING;
            }
            // If there is one fail
            if (isset($statusValueList[self::STATUS_FAILED])) {
                $status = self::STATUS_FAILED;
            }
        }
        return $status;
    }

    /**
     * Check current step status and if we can go further in the workflow
     *
     * @param string $workflowProcessId the wf process identifier
     *
     * @return string
     */
    public function getCurrentStepStatus(string $workflowProcessId) : string
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $contextDto = $this->di->get('contextStorage')->get($workflowProcessId);
        $currentStepPos = $contextDto->getWorkflowCurrentPosition();
        $stepNode = $contextDto->getWorkflowStepListByPosition($currentStepPos);
        $stepStatusList = [];

        if ($this->isStepParallelized($stepNode)) {
            foreach ($stepNode as $stepHash) {
                array_push($stepStatusList, $this->getStepStatusByJobStatus($stepHash));
            }
            $status = $this->aggregateStatus($stepStatusList);
        } else {
            $status = $this->getStepStatusByJobStatus($stepNode);
        }

        return $status;
    }

    /**
     * Parses and stores the step's job results related to the given wf process id and step
     * Result is stored in the context as below :
     *  {
     *      'jobList' : [
     *          {
     *              'jobId' : 0,
     *              'status' : 'SUCCESS',
     *              'result' : {
     *                  // biz data
     *              }
     *          },
     *      ]
     *  }
     *
     * @param string $workflowProcessId the wf process identifier to which belongs the step's job result
     * @param string $stepCode          the step to which belongs the job
     * @param int    $jobId             the job identifier related to the step
     * @param array  $resultHash        the result data
     *
     * @throws WorkflowException in case of no job found
     *
     * @return void
     */
    public function processStepJobResult(string $workflowProcessId, string $stepCode, int $jobId, array $resultHash)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $stepHash = $this->getContextWorkflowStep($workflowProcessId, $stepCode);
        if (!isset($stepHash['jobList']) || !isset($stepHash['jobList'][$jobId])) {
            throw new WorkflowException('Cannot find any job');
        }
        $jobHash['status'] = $resultHash['status'] ?? self::STATUS_FAILED;
        $jobHash['result'] = $resultHash['data'] ?? [];
        $jobHash['finishedAt'] = $resultHash['finishedAt'] ?? date(ContextStorageService::DATE_FORMAT);
        $this->di->get('contextStorage')->updateWorkflowStepJob(
            $workflowProcessId,
            $stepCode,
            $jobId,
            $jobHash
        );

        if ($stepHash['jobList'][$jobId]['status'] == self::STATUS_FAILED) {
            $this->processStepJobFailure($workflowProcessId, $stepCode, $jobId, $resultHash);
        }
    }

    /**
     * Process failure on step job
     *
     * @param string $workflowProcessId the wf process identifier to which belongs the step's job result
     * @param string $stepCode          the step to which belongs the job
     * @param int    $jobId             the job identifier related to the step
     * @param array  $resultHash        the result data
     *
     * @return void
     */
    private function processStepJobFailure(string $workflowProcessId, string $stepCode, int $jobId, array $resultHash)
    {
        // check step conf to see if the step is "blocking"
        // set the WF status accordingly
        $this->finalize($workflowProcessId, self::STATUS_FAILED, $resultHash['info'] ?? '');
    }

    /**
     * Registers in context the step's job related to the given wf process id
     * Stores in context as below :
     *  {
     *      'jobList' : [
     *          {
     *              'jobId' : 0,
     *              'status' : 'NOT_STARTED',
     *              'result' : {}
     *          },
     *      ]
     *  }
     *
     * @param string $workflowProcessId the wf process identifier to which belongs the step's job result
     * @param string $stepCode          the step to which belongs the job
     * @param int    $jobId             the job identifier related to the step
     *
     * @return void
     */
    public function registerStepJob($workflowProcessId, $stepCode, $jobId)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        // q&d search in context the job for which saving the result
        $stepHash = $this->getContextWorkflowStep($workflowProcessId, $stepCode);
        if (!isset($stepHash['jobList'])) {
            $stepHash['jobList'] = [];
        }
        // TODO check if job hasn't been registered yet
        $stepHash['jobList'][] = [
            'id' => $jobId,
            'status' => self::STATUS_NO_STARTED,
            'registeredAt' => date(ContextStorageService::DATE_FORMAT),
            'result' => []
        ];
        $this->di->get('contextStorage')->updateWorkflowStep($workflowProcessId, $stepCode, $stepHash);
    }

    /**
     * Updates in context the step's job status related to the given wf process id
     * Stores in context as below :
     *  {
     *      'jobList' : [
     *          {
     *              'jobId' : 0,
     *              'status' : 'NOT_STARTED',
     *
     *              'result' : {}
     *          },
     *      ]
     *  }
     *
     * @param string $workflowProcessId the wf process identifier to which belongs the step's job result
     * @param string $stepCode          the step to which belongs the job
     * @param int    $jobId             the job identifier related to the step
     * @param string $workerHostname    the worker hostnampe on which the step has been executed
     *
     * @return void
     */
    public function registerStepJobStarted($workflowProcessId, $stepCode, $jobId, $workerHostname)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $this->di->get('contextStorage')->updateWorkflowStepJob(
            $workflowProcessId,
            $stepCode,
            $jobId,
            [
                'status' => self::STATUS_STARTED,
                'startedAt' => date(ContextStorageService::DATE_FORMAT),
                'worker' => $workerHostname
            ]
        );
    }

    /**
     * Access step
     *
     * @param string $workflowProcessId the wf process identifier to which belongs the step's job result
     * @param string $stepCode          the step to which belongs the job
     *
     * @return mixed
     */
    private function getContextWorkflowStep($workflowProcessId, $stepCode)
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $contextDto = $this->di->get('contextStorage')->get($workflowProcessId);
        $workflowStepList = $contextDto->getWorkflowStepList();
        foreach ($workflowStepList as &$stepNode) {
            if ($this->isStepParallelized($stepNode)) {
                foreach ($stepNode as &$stepHash) {
                    if ($stepHash['name'] == $stepCode) {
                        return $stepHash;
                    }
                }
            } else {
                $stepHash = $stepNode;
                if ($stepHash['name'] == $stepCode) {
                    return $stepHash;
                }
            }
        }
    }

    /**
     * Check whether the workflow is running or not
     *
     * @param string $workflowProcessId the wf process identifier to which belongs the step's job result
     *
     * @return bool
     */
    private function isRunning(string $workflowProcessId) : bool
    {
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        $contextDto = $this->di->get('contextStorage')->get($workflowProcessId);
        return ($contextDto->getWorkflowStatus() == self::STATUS_STARTED);
    }

    /**
     * Check if the step is parallelized
     *
     * @param string $stepNode step node
     *
     * @return bool
     */
    private function isStepParallelized($stepNode) : bool
    {
        // Deals w/ parallelized task xxx to unitest
        // To distinguish single step hash :
        // { "name" : "step_foo"}
        // from
        // [
        //      { "name" : "step_foo"},
        //      { "name" : "step_bar"}
        // ]
        $this->di->get('logr')->debug(json_encode(func_get_args()));
        return !(array_keys($stepNode) !== array_keys(array_keys($stepNode)));
    }
}
