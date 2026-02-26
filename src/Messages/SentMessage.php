<?php

namespace OpenCompany\Chatogrator\Messages;

use OpenCompany\Chatogrator\Contracts\Adapter;

class SentMessage extends Message
{
    protected Adapter $adapter;

    public function setAdapter(Adapter $adapter): static
    {
        $this->adapter = $adapter;

        return $this;
    }

    public function edit(string|PostableMessage $content): static
    {
        $message = is_string($content) ? PostableMessage::text($content) : $content;
        $this->adapter->editMessage($this->threadId, $this->id, $message);

        return $this;
    }

    public function delete(): void
    {
        $this->adapter->deleteMessage($this->threadId, $this->id);
    }

    public function addReaction(string $emoji): void
    {
        $this->adapter->addReaction($this->threadId, $this->id, $emoji);
    }

    public function removeReaction(string $emoji): void
    {
        $this->adapter->removeReaction($this->threadId, $this->id, $emoji);
    }

    public function pin(): void
    {
        $this->adapter->pinMessage($this->threadId, $this->id);
    }

    public function unpin(): void
    {
        $this->adapter->unpinMessage($this->threadId, $this->id);
    }
}
