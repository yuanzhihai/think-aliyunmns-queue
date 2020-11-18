<?php
/**
 * think-aliyunmns-queue
 *
 * @author    yzh52521
 * @link      https://github.com/yzh52521/think-aliyunmns-queue
 * @copyright 2020 yzh52521 all rights reserved.
 * @license   http://www.apache.org/licenses/LICENSE-2.0
 */

namespace think\queue\job;

use AliyunMNS\Responses\ReceiveMessageResponse;
use think\queue\connector\Aliyun as AliyunQueue;
use think\queue\Job;

class Aliyun extends Job
{

    /**
     * The Iron queue instance.
     *
     * @var AliyunQueue
     */
    protected $aliyunmns;

    /**
     * The IronMQ message instance.
     *
     * @var object
     */
    protected $job;

    /**
     * AliyunMNS constructor.
     *
     * @param \think\queue\connector\Aliyun $aliyunmns
     * @param ReceiveMessageResponse $job
     * @param string $queue
     */
    public function __construct(AliyunQueue $aliyunmns, $job, $queue)
    {
        $this->aliyunmns = $aliyunmns;
        $this->job       = $job;
        $this->queue     = $queue;
    }

    /**
     * Fire the job.
     *
     * @access public
     * @return void
     */
    public function fire()
    {
        $this->resolveAndFire(json_decode($this->getRawBody(), true));
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @access public
     * @return int
     */
    public function attempts()
    {
        return (int)$this->job->getDequeueCount();
    }

    /**
     * @access public
     * 删除任务
     */
    public function delete()
    {
        parent::delete();

        $this->aliyunmns->deleteMessage($this->queue, $this->job->getReceiptHandle());
    }

    /**
     * 重新发布任务
     *
     * @access public
     * @param int $delay
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $this->delete();

        $this->aliyunmns->release($this->queue, $this->job, $delay);
    }

    /**
     * Get the raw body string for the job.
     *
     * @access public
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->getMessageBody();
    }

}
