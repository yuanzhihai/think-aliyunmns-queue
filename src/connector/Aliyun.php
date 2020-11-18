<?php
/**
 * think-aliyunmns-queue
 *
 * @author    yzh52521
 * @link      https://github.com/yzh52521/think-aliyunmns-queue
 * @copyright 2020 yzh52521 all rights reserved.
 * @license   http://www.apache.org/licenses/LICENSE-2.0
 */

namespace think\queue\connector;

use AliyunMNS\Client;
use AliyunMNS\Exception\MnsException;
use AliyunMNS\Requests\SendMessageRequest;
use think\exception\ValidateException;
use think\facade\Log;
use think\queue\Connector;
use think\queue\job\Aliyun as AliyunJob;

class Aliyun extends Connector
{

    /**
     * @var array
     */
    protected $options = [
        'accessId'  => '',
        'accessKey' => '',
        'endPoint'  => '',
        'wait'      => 30,
        'default'   => 'default',
    ];

    /**
     * @var \AliyunMNS\Client
     */
    protected $client;

    /**
     * AliyunMNS constructor.
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        if (empty($this->options['accessId'])) {
            throw new ValidateException("Queue key accessId not config.");
        }

        if (empty($this->options['accessKey'])) {
            throw new ValidateException("Queue key accessKey not config.");
        }

        if (empty($this->options['endPoint'])) {
            throw new ValidateException("Queue key endPoint not config.");
        }

        $this->client = new Client(
            $this->options['endPoint'],
            $this->options['accessId'],
            $this->options['accessKey']
        );
    }

    /**
     * 发送一个消息
     * @access public
     * @param mixed $job
     * @param string $data
     * @param string $queue
     * @return string
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw(0, $queue, $this->createPayload($job, $data));
    }

    /**
     * 发送一个延迟消息
     * @access public
     * @param int $delay
     * @param mixed $job
     * @param string $data
     * @param string $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw($delay, $queue, $this->createPayload($job, $data));
    }

    /**
     * 重新发布这个任务
     * @access public
     * @param string $queue
     * @param AliyunJob $rawJob
     * @param int $delay
     * @return string
     */
    public function release($queue, $rawJob, $delay)
    {
        return $this->pushRaw($delay, $queue, $rawJob->getRawBody(), $rawJob->attempts());
    }

    /**
     * 推送一个任务到消息队列
     *
     * @access protected
     * @param int $delay
     * @param string $queue
     * @param mixed $payload
     * @param int $attempts
     * @return string
     */
    protected function pushRaw($delay, $queue, $payload, $attempts = 0)
    {
        $queue = $this->resolveQueue($queue);

        // 生成一个SendMessageRequest对象
        // SendMessageRequest对象本身也包含了DelaySeconds和Priority属性可以设置。
        // 对于Message的属性，请参考help.aliyun.com/document_detail/27477.html
        $request = new SendMessageRequest($payload, $delay);

        try {
            $message = $queue->sendMessage($request);
        } catch (MnsException $e) {
            $this->log($e);
            return null;
        }

        return $message->getMessageId();
    }

    /**
     * 获取队列实例
     *
     * @access protected
     * @param string $queueName
     * @return \AliyunMNS\Queue
     */
    protected function resolveQueue($queueName)
    {
        if (empty($queueName)) {
            $queueName = $this->options['default'];
        }

        return $this->client->getQueueRef($queueName);
    }

    /**
     * 获取一个任务
     *
     * @access public
     * @param string $queue
     * @return \think\queue\job\Aliyun
     */
    public function pop($queue = null)
    {
        $queue = $this->resolveQueue($queue);

        try {
            // 1. 直接调用receiveMessage函数
            // 1.1 receiveMessage函数接受waitSeconds参数，无特殊情况这里都是建议设置为30
            // 1.2 waitSeconds非0表示这次receiveMessage是一次http long polling，如果queue内刚好没有message，那么这次request会在server端等到queue内有消息才返回。最长等待时间为waitSeconds的值，最大为30。
            $message = $queue->receiveMessage(
                $this->options['wait']
            );
            // 2. 获取ReceiptHandle，这是一个有时效性的Handle，可以用来设置Message的各种属性和删除Message。具体的解释请参考：help.aliyun.com/document_detail/27477.html 页面里的ReceiptHandle
            //	   $receiptHandle = $res->getReceiptHandle();
        } catch (MnsException $e) {
            $this->log($e);
            return null;
        }

        return new AliyunMNSJob(
            $this,
            $message,
            $queue->getQueueName()
        );
    }

    /**
     * 删除消息
     *
     * @access public
     * @param string $queue
     * @param string $id
     * @return bool
     */
    public function deleteMessage($queue, $id)
    {
        $queue = $this->resolveQueue($queue);

        try {
            $queue->deleteMessage($id);
        } catch (MnsException $e) {
            $this->log($e);
            return false;
        }

        return true;
    }

    /**
     * 清理资源
     */
    public function __destruct()
    {
        if (null != $this->client) {
            $this->client = null;
        }
    }

    /**
     * @access protected
     * @param \Exception $e
     */
    protected function log(\Exception $e)
    {
        Log::error($e->getMessage() . "\n" . $e->getTraceAsString());
    }

}
