<?php
/**
 * Created by PhpStorm.
 * User: rbarbu
 * Date: 04/03/16
 * Time: 14:55
 */

namespace rombar\PgExplorerBundle\Models;

class Utils
{

    /**
     * @param $old
     * @param $new
     * @return string
     */
    public static function stringDiff($old, $new)
    {
        if(!is_array($old)){
            $old = str_split($old);
        }
        if(!is_array($new)){
            $new = str_split($new);
        }

        if(count($old) < count($new)){
            return Utils::stringDiff($new, $old);
        }

        $diff = [];
        $refOld = $old;
        $refNew = $new;
        foreach($refOld as $key => $letter){

            if(!isset($new[$key])){
                $diff[] = $letter;
                unset($refOld[$key]);

            }elseif($letter != $new[$key]){
                $diff[] = $letter;
                unset($refOld[$key]);

                break;
            }else{
                unset($refOld[$key]);
                unset($refNew[$key]);

            }
        }
        $refOld = array_values($refOld);
        $refNew = array_values($refNew);

        if(count($refOld)){
            $diff[] = Utils::stringDiff($refOld, $refNew);
        }

        return implode('', $diff);
    }
}