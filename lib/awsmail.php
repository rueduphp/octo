<?php

namespace Octo;

class Awsmail extends \Swift_SmtpTransport
{

    /**
     * @var array
     */
    private $rawResponses = array();

    /**
     * Run a command against the buffer, expecting the given response codes.
     *
     * If no response codes are given, the response will not be validated.
     * If codes are given, an exception will be thrown on an invalid response.
     *
     * @param string   $command
     * @param int[]    $codes
     * @param null|string[] $failures An array of failures by-reference
     *
     * @return string
     */
    public function executeCommand($command, $codes = [], &$failures = null)
    {
        $response = parent::executeCommand($command, $codes, $failures);
        $this->rawResponses[] = $response;

        return $response;
    }

    /**
     * @param string $host
     * @param int $port
     * @param null $security
     * @return Awsmail
     */
    public static function newInstance(string $host = 'localhost', int $port = 25,  $security = null): self
    {
        return new self($host, $port, $security);
    }

    /**
     * @return null|string
     */
    public function getMessageId(): ?string
    {
        $messageId = null;

        foreach ($this->rawResponses as $e) {
            preg_match('/(?<=250 ok\s)[^\s]*/i', $e, $matched);

            if (sizeof($matched) > 0) {
                $messageId = $matched[0];
            }
        }

        return $messageId;
    }

    /**
     * @return array
     */
    public function getRawResponses(): array
    {
        return $this->rawResponses;
    }
}
