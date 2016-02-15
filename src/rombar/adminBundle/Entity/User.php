<?php

namespace rombar\adminBundle\Entity;

use FOS\UserBundle\Entity\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
/**
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User extends BaseUser
{
    
  /**
   * @ORM\Id
   * @ORM\Column(type="integer")
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  protected $id;
  
   public function getEnabled() {
        return $this->enabled;
    }

    public function getLocked() {
        return $this->locked;
    }

    public function getExpired() {
        return $this->expired;
    }

    public function getExpiresAt() {
        return $this->expiresAt;
    }

    public function getCredentialsExpired() {
        return $this->credentialsExpired;
    }

    public function getCredentialsExpireAt() {
        return $this->credentialsExpireAt;
    }
}
