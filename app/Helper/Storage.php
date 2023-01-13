<?php

namespace App\Helper;

use App\Helper\Uuid;

class Storage
{
  public static function storeImage($fileImage)
  {
    $ext = $fileImage->getClientOriginalExtension();
    $name = UUid::generateUuid() . "." . $ext;
    $fileImage->move(base_path("public/images"), $name);

    return $name;
  }

  public static function getImageUrl($name)
  {
    return url('/images/' . $name);
  }
}
