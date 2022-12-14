<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */

namespace Twilio\Rest\Api\V2010\Account;

use Twilio\Options;
use Twilio\Values;

abstract class ConferenceOptions {
    /**
     * @param string $dateCreatedBefore Filter by date created
     * @param string $dateCreated Filter by date created
     * @param string $dateCreatedAfter Filter by date created
     * @param string $dateUpdatedBefore Filter by date updated
     * @param string $dateUpdated Filter by date updated
     * @param string $dateUpdatedAfter Filter by date updated
     * @param string $friendlyName Filter by friendly name
     * @param string $status The status of the conference
     * @return ReadConferenceOptions Options builder
     */
    public static function read($dateCreatedBefore = Values::NONE, $dateCreated = Values::NONE, $dateCreatedAfter = Values::NONE, $dateUpdatedBefore = Values::NONE, $dateUpdated = Values::NONE, $dateUpdatedAfter = Values::NONE, $friendlyName = Values::NONE, $status = Values::NONE) {
        return new ReadConferenceOptions($dateCreatedBefore, $dateCreated, $dateCreatedAfter, $dateUpdatedBefore, $dateUpdated, $dateUpdatedAfter, $friendlyName, $status);
    }

    /**
     * @param string $status Specifying completed will end the conference and kick
     *                       all participants
     * @param string $announceUrl The announce_url
     * @param string $announceMethod The announce_method
     * @return UpdateConferenceOptions Options builder
     */
    public static function update($status = Values::NONE, $announceUrl = Values::NONE, $announceMethod = Values::NONE) {
        return new UpdateConferenceOptions($status, $announceUrl, $announceMethod);
    }
}

class ReadConferenceOptions extends Options {
    /**
     * @param string $dateCreatedBefore Filter by date created
     * @param string $dateCreated Filter by date created
     * @param string $dateCreatedAfter Filter by date created
     * @param string $dateUpdatedBefore Filter by date updated
     * @param string $dateUpdated Filter by date updated
     * @param string $dateUpdatedAfter Filter by date updated
     * @param string $friendlyName Filter by friendly name
     * @param string $status The status of the conference
     */
    public function __construct($dateCreatedBefore = Values::NONE, $dateCreated = Values::NONE, $dateCreatedAfter = Values::NONE, $dateUpdatedBefore = Values::NONE, $dateUpdated = Values::NONE, $dateUpdatedAfter = Values::NONE, $friendlyName = Values::NONE, $status = Values::NONE) {
        $this->options['dateCreatedBefore'] = $dateCreatedBefore;
        $this->options['dateCreated'] = $dateCreated;
        $this->options['dateCreatedAfter'] = $dateCreatedAfter;
        $this->options['dateUpdatedBefore'] = $dateUpdatedBefore;
        $this->options['dateUpdated'] = $dateUpdated;
        $this->options['dateUpdatedAfter'] = $dateUpdatedAfter;
        $this->options['friendlyName'] = $friendlyName;
        $this->options['status'] = $status;
    }

    /**
     * Only show conferences that started on this date, given as `YYYY-MM-DD`. You can also specify inequality, such as `DateCreated&lt;=YYYY-MM-DD` for conferences that started at or before midnight on a date, and `DateCreated&gt;=YYYY-MM-DD` for conferences that started at or after midnight on a date.
     * 
     * @param string $dateCreatedBefore Filter by date created
     * @return $this Fluent Builder
     */
    public function setDateCreatedBefore($dateCreatedBefore) {
        $this->options['dateCreatedBefore'] = $dateCreatedBefore;
        return $this;
    }

    /**
     * Only show conferences that started on this date, given as `YYYY-MM-DD`. You can also specify inequality, such as `DateCreated&lt;=YYYY-MM-DD` for conferences that started at or before midnight on a date, and `DateCreated&gt;=YYYY-MM-DD` for conferences that started at or after midnight on a date.
     * 
     * @param string $dateCreated Filter by date created
     * @return $this Fluent Builder
     */
    public function setDateCreated($dateCreated) {
        $this->options['dateCreated'] = $dateCreated;
        return $this;
    }

    /**
     * Only show conferences that started on this date, given as `YYYY-MM-DD`. You can also specify inequality, such as `DateCreated&lt;=YYYY-MM-DD` for conferences that started at or before midnight on a date, and `DateCreated&gt;=YYYY-MM-DD` for conferences that started at or after midnight on a date.
     * 
     * @param string $dateCreatedAfter Filter by date created
     * @return $this Fluent Builder
     */
    public function setDateCreatedAfter($dateCreatedAfter) {
        $this->options['dateCreatedAfter'] = $dateCreatedAfter;
        return $this;
    }

    /**
     * Only show conferences that were last updated on this date, given as `YYYY-MM-DD`. You can also specify inequality, such as `DateUpdated&lt;=YYYY-MM-DD` for conferences that were last updated at or before midnight on a date, and `DateUpdated&gt;=YYYY-MM-DD` for conferences that were updated at or after midnight on a date.
     * 
     * @param string $dateUpdatedBefore Filter by date updated
     * @return $this Fluent Builder
     */
    public function setDateUpdatedBefore($dateUpdatedBefore) {
        $this->options['dateUpdatedBefore'] = $dateUpdatedBefore;
        return $this;
    }

    /**
     * Only show conferences that were last updated on this date, given as `YYYY-MM-DD`. You can also specify inequality, such as `DateUpdated&lt;=YYYY-MM-DD` for conferences that were last updated at or before midnight on a date, and `DateUpdated&gt;=YYYY-MM-DD` for conferences that were updated at or after midnight on a date.
     * 
     * @param string $dateUpdated Filter by date updated
     * @return $this Fluent Builder
     */
    public function setDateUpdated($dateUpdated) {
        $this->options['dateUpdated'] = $dateUpdated;
        return $this;
    }

    /**
     * Only show conferences that were last updated on this date, given as `YYYY-MM-DD`. You can also specify inequality, such as `DateUpdated&lt;=YYYY-MM-DD` for conferences that were last updated at or before midnight on a date, and `DateUpdated&gt;=YYYY-MM-DD` for conferences that were updated at or after midnight on a date.
     * 
     * @param string $dateUpdatedAfter Filter by date updated
     * @return $this Fluent Builder
     */
    public function setDateUpdatedAfter($dateUpdatedAfter) {
        $this->options['dateUpdatedAfter'] = $dateUpdatedAfter;
        return $this;
    }

    /**
     * Only show results who's friendly name exactly matches the string
     * 
     * @param string $friendlyName Filter by friendly name
     * @return $this Fluent Builder
     */
    public function setFriendlyName($friendlyName) {
        $this->options['friendlyName'] = $friendlyName;
        return $this;
    }

    /**
     * A string representing the status of the conference. May be `init`, `in-progress`, or `completed`.
     * 
     * @param string $status The status of the conference
     * @return $this Fluent Builder
     */
    public function setStatus($status) {
        $this->options['status'] = $status;
        return $this;
    }

    /**
     * Provide a friendly representation
     * 
     * @return string Machine friendly representation
     */
    public function __toString() {
        $options = array();
        foreach ($this->options as $key => $value) {
            if ($value != Values::NONE) {
                $options[] = "$key=$value";
            }
        }
        return '[Twilio.Api.V2010.ReadConferenceOptions ' . implode(' ', $options) . ']';
    }
}

class UpdateConferenceOptions extends Options {
    /**
     * @param string $status Specifying completed will end the conference and kick
     *                       all participants
     * @param string $announceUrl The announce_url
     * @param string $announceMethod The announce_method
     */
    public function __construct($status = Values::NONE, $announceUrl = Values::NONE, $announceMethod = Values::NONE) {
        $this->options['status'] = $status;
        $this->options['announceUrl'] = $announceUrl;
        $this->options['announceMethod'] = $announceMethod;
    }

    /**
     * Specifying `completed` will end the conference and kick all participants
     * 
     * @param string $status Specifying completed will end the conference and kick
     *                       all participants
     * @return $this Fluent Builder
     */
    public function setStatus($status) {
        $this->options['status'] = $status;
        return $this;
    }

    /**
     * The announce_url
     * 
     * @param string $announceUrl The announce_url
     * @return $this Fluent Builder
     */
    public function setAnnounceUrl($announceUrl) {
        $this->options['announceUrl'] = $announceUrl;
        return $this;
    }

    /**
     * The announce_method
     * 
     * @param string $announceMethod The announce_method
     * @return $this Fluent Builder
     */
    public function setAnnounceMethod($announceMethod) {
        $this->options['announceMethod'] = $announceMethod;
        return $this;
    }

    /**
     * Provide a friendly representation
     * 
     * @return string Machine friendly representation
     */
    public function __toString() {
        $options = array();
        foreach ($this->options as $key => $value) {
            if ($value != Values::NONE) {
                $options[] = "$key=$value";
            }
        }
        return '[Twilio.Api.V2010.UpdateConferenceOptions ' . implode(' ', $options) . ']';
    }
}