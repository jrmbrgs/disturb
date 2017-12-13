<?php
namespace Vpg\Disturb\Workflow;

use \Phalcon\DI;

/**
 * Class WorkflowConfigDtoFactory
 *
 * @license  https://github.com/vpg/disturb/blob/master/LICENSE MIT Licence
 */
class WorkflowConfigDtoFactory
{
    /**
     * List of authorized config file extension type
     *
     * @const array CONFIG_FILE_EXT_LIST
     */
    const CONFIG_FILE_EXT_LIST = ['json', 'php'];

    /**
     * Get workflow config dto
     *
     * @param string $workflowConfigFilePath
     *
     * @return \Vpg\Disturb\Workflow\WorkflowConfigDto
     *
     * @throws WorkflowException
     */
    public static function get(string $workflowConfigFilePath) : WorkflowConfigDto
    {
        if (!file_exists($workflowConfigFilePath)) {
            throw new WorkflowException('Workflow config file not found');
        }

        // get and check file ext
        $workflowConfigFileExtension = pathinfo($workflowConfigFilePath, PATHINFO_EXTENSION);
        if (!in_array($workflowConfigFileExtension, self::CONFIG_FILE_EXT_LIST)) {
            throw new WorkflowException('Workflow config file only authorize extension : ' .
                implode(',', self::CONFIG_FILE_EXT_LIST)
            );
        }

        Di::getDefault()->get('logr')->info("Loading Workflow config from '$workflowConfigFilePath'");

        // instanciate config adapter
        $workflowConfigAdpter = '\Phalcon\Config\Adapter\\' . ucfirst($workflowConfigFileExtension);

        return new WorkflowConfigDto(new $workflowConfigAdpter($workflowConfigFilePath));
    }
}
