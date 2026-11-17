<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support;

use Doctrine\ORM\Mapping as ORM;

/**
 * An entity of the "host application", sharing the entity manager with the package entities.
 */
#[ORM\Entity]
#[ORM\Table(name: 'host_note')]
class HostNote
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $text,
    ) {}

    public function text(): string
    {
        return $this->text;
    }

    public function rewrite(string $text): void
    {
        $this->text = $text;
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
