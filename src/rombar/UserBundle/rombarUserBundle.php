<?php

namespace rombar\UserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class rombarUserBundle extends Bundle
{
    public function getParent()
  {
    return 'FOSUserBundle';
  }
}
