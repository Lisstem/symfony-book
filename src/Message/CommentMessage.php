<?php

namespace App\Message;

final class CommentMessage
{
    public function __construct(
        private readonly int   $id,
        private readonly array $context = [],
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContext(): array
    {
        return $this->context;
    }


}
