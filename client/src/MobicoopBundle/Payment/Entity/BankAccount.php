<?php

/**
 * Copyright (c) 2020, MOBICOOP. All rights reserved.
 * This project is dual licensed under AGPL and proprietary licence.
 ***************************
 *    This program is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU Affero General Public License as
 *    published by the Free Software Foundation, either version 3 of the
 *    License, or (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU Affero General Public License for more details.
 *
 *    You should have received a copy of the GNU Affero General Public License
 *    along with this program.  If not, see <gnu.org/licenses>.
 ***************************
 *    Licence MOBICOOP described in the file
 *    LICENSE
 **************************/

namespace Mobicoop\Bundle\MobicoopBundle\Payment\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Mobicoop\Bundle\MobicoopBundle\Api\Entity\ResourceInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Mobicoop\Bundle\MobicoopBundle\User\Entity\User;
use Mobicoop\Bundle\MobicoopBundle\Geography\Entity\Address;

/**
 * A Bank account
 * @author Maxime Bardot <maxime.bardot@mobicoop.org>
 */
class BankAccount implements ResourceInterface, \JsonSerializable
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    /**
     * @var int The id of this bank account
     */
    private $id;

    /**
     * @var string|null The iri of this bank account.
     *
     */
    private $iri;

    /**
     * @var string|null The litteral name of the user owning this bank account
     */
    private $userLitteral;

    /**
     * @var Address|null The litteral name of the user owning this bank account
     *
     * @Groups({"post"})
     */
    private $address;
    
    /**
     * @var string The iban number of this bank account
     *
     * @Assert\NotBlank
     * @Assert\Iban
     * @Groups({"post"})
     */
    private $iban;

    /**
     * @var string The bic number of this bank account
     *
     * @Assert\NotBlank
     * @Assert\Bic
     * @Groups({"post"})
     */
    private $bic;

    /**
     * @var string|null A comment for this bank account
     *
     * @Groups({"post"})
     */
    private $comment;

    /**
     * @var int The status of this bank account (0 : Inactive, 1 : Active)
     *
     * @Groups({"post"})
     */
    private $status;

    /**
     * @var \DateTimeInterface Creation date.
     */
    private $createdDate;

    
    public function __construct($id=null)
    {
        if ($id) {
            $this->setId($id);
            $this->setIri("/bank_accounts/".$id);
        }
        $this->images = new ArrayCollection();
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getIri()
    {
        return $this->iri;
    }
    
    public function setIri($iri)
    {
        $this->iri = $iri;
    }

    public function getUserLitteral(): ?String
    {
        return $this->userLitteral;
    }

    public function setUserLitteral(?String $userLitteral)
    {
        $this->userLitteral = $userLitteral;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address)
    {
        $this->address = $address;
    }

    public function getIban(): ?String
    {
        return $this->iban;
    }

    public function setIban(?String $iban)
    {
        $this->iban = $iban;
    }

    public function getBic(): ?String
    {
        return $this->bic;
    }

    public function setBic(?String $bic)
    {
        $this->bic = $bic;
    }

    public function getComment(): ?String
    {
        return $this->comment;
    }

    public function setComment(?String $comment)
    {
        $this->comment = $comment;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $status)
    {
        $this->status = $status;
    }

    public function getCreatedDate(): ?\DateTimeInterface
    {
        return $this->createdDate;
    }

    public function setCreatedDate(\DateTimeInterface $createdDate)
    {
        $this->createdDate = $createdDate;
    }

    public function jsonSerialize()
    {
        return
            [
                'id'                => $this->getId(),
                'iri'               => $this->getIri(),
                'userLitteral'      => $this->getUserLitteral(),
                'iban'              => $this->getIban(),
                'bic'               => $this->getBic(),
                'comment'           => $this->getComment(),
                'status'            => $this->getStatus(),
                'createdDate'       => $this->getCreatedDate()
            ];
    }
}