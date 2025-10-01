<?php

namespace SimpleBBS\Auth;

class User
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $email,
        private readonly ?string $avatarUrl = null
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }
}
