<?php
/**
 * Table Definition for subscription_queue
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Subscription_queue extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'subscription_queue';       // table name
    public $subscriber;
    public $subscribed;
    public $created;

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Subscription_queue',$k,$v); }

    /* Pkey get */
    function pkeyGet($k)
    { return Memcached_DataObject::pkeyGet('Subscription_queue',$k); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'Holder for subscription requests awaiting moderation.',
            'fields' => array(
                'subscriber' => array('type' => 'int', 'not null' => true, 'description' => 'remote or local profile making the request'),
                'subscribed' => array('type' => 'int', 'not null' => true, 'description' => 'remote or local profile being subscribed to'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
            ),
            'primary key' => array('subscriber', 'subscribed'),
            'indexes' => array(
                'group_join_queue_profile_id_created_idx' => array('subscriber', 'created'),
                'group_join_queue_group_id_created_idx' => array('subscribed', 'created'),
            ),
            'foreign keys' => array(
                'group_join_queue_subscriber_fkey' => array('profile', array('subscriber' => 'id')),
                'group_join_queue_subscribed_fkey' => array('profile', array('subscribed' => 'id')),
            )
        );
    }

    public static function saveNew(Profile $subscriber, Profile $other)
    {
        $rq = new Group_join_queue();
        $rq->subscriber = $subscriber->id;
        $rq->subscribed = $subscribed->id;
        $rq->created = common_sql_now();
        $rq->insert();
        return $rq;
    }

    /**
     * Send notifications via email etc to group administrators about
     * this exciting new pending moderation queue item!
     */
    public function notify()
    {
        $subscriber = Profile::staticGet('id', $this->subscriber);
        $subscribed = Profile::staticGet('id', $this->subscribed);
        mail_notify_subscription_pending($subscribed, $subscriber);
    }
}
