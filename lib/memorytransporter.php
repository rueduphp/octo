<?php

namespace Octo;

class Memorytransporter extends Transporter
{
    /**
     * {@inheritdoc}
     */
    public function send(\Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $messages = $this->messages();

        $this->beforeSendPerformed($message);

        $messages[] = $message;

        Registry::set('core.mails', $messages);

        return $this->numberOfRecipients($message);
    }

    /**
     * Retrieve the collection of messages.
     *
     * @return array
     */
    public function messages()
    {
        return Registry::get('core.mails', []);
    }

    /**
     * @return Memorytransporter
     */
    public function flush(): self
    {
        Registry::set('core.mails', []);

        return $this;
    }
}
