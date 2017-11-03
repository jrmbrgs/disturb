<?
/*
 * This file is part of the Disturb package.
 *
 * (c) Matthieu Ventura <mventura@voyageprive.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Disturb\Commands;

class Workflow
{
    /**
     * Start workflow by sending a message in related topic
     *
     * @param String $workflowName Workflow name
     * @param String $workflowId   Workflow id
     * @param Array  $payloadHash  List of params
     *
     */
    public static function start(string $workflowName, string $workflowId, array $payloadHash)
    {
        $brokers = 'localhost';
        $msg = '{"contract":"'.$workflowId.'", "type" : "WF-CONTROL", "action":"start"}';

        //send message with givens params
        $kafkaProducer = new \RdKafka\Producer();
        $kafkaProducer->addBrokers($brokers);
        $topicName = 'disturb-' . $workflowName . '-manager'; //xxx create service to manage topic name

        $kafkaTopic = $kafkaProducer->newTopic($topicName);
        $kafkaTopic->produce(RD_KAFKA_PARTITION_UA, 0, "$msg");
    }
}
