<?php

namespace rombar\PgExplorerBundle\Models\dbElements;
use rombar\PgExplorerBundle\Exceptions\ElementNotFoundException;
use rombar\PgExplorerBundle\Models\PgAnalyzer;

/**
 * Description of PgElement
 *
 * @author barbu
 */
class PgElement {

    protected $oid;

    protected $name;

    protected $owner;

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if(!isset($this->$name) || !empty($value)){
            $this->$name = $value;
        }
    }

    /**
     * @param $name
     * @return string|null
     */
    public function __get($name)
    {
        if(isset($this->$name)){
            return $this->$name;
        }else{
            return null;
        }
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return $this|null
     * @throws \rombar\PgExplorerBundle\Exceptions\ElementNotFoundException
     */
    public function __call($method, $arguments)
    {
        /**
         * To fill the collections. Method as to be like add<Pg element>.
         * Example addTable(Table $table) will do $this->tables[$table->getOid()] = $table
         */
        
        if(preg_match('#^add([[:alnum:]]{1,})$#', $method, $matches)
            && count($arguments) == 1
            && is_object($arguments[0])){
            $element = $matches[1];
            $attribut = lcfirst($element).'s';
            
            if(is_a($arguments[0], PgAnalyzer::NAME_SPACE.$element) && is_array($this->$attribut)){
                //\Doctrine\Common\Util\Debug::dump($arguments[0]->getOid());
                $tableau = &$this->$attribut;

                $tableau[$arguments[0]->getOid()] = $arguments[0];


            }
            return $this;
        }elseif(preg_match('#^getA(Table|Sequence|Column|View|Function|Type)$#', $method, $matches)
            && count($arguments) == 1){
            $element = $matches[1];
            $attribut = strtolower($element).'s';
            $tableau = $this->$attribut;
            if(!isset($tableau[$arguments[0]]) && preg_match('#^[0-9]+$#', $arguments[0])) {
                throw new ElementNotFoundException($element.' with oid ' . $arguments[0] . ' not found');
            }elseif(!isset($tableau[$arguments[0]])){//Check by name
                $attribut2 = $attribut . 'ByName';
                $tableauByName = (isset($this->$attribut2) && is_array($this->$attribut2)) ? $this->$attribut2 : [];
                if(isset($tableauByName[$arguments[0]]) && isset($tableau[$tableauByName[$arguments[0]]])){
                    return $tableau[$tableauByName[$arguments[0]]];
                }else{
                    throw new ElementNotFoundException($element.' with name ' . $arguments[0] . ' not found');
                }
            }
            return $tableau[$arguments[0]];
        }elseif(preg_match('#^get([[:alnum:]]{1,})$#', $method, $matches) && count($arguments) == 0){
             return $this->__get(lcfirst($matches[1]));
             
        }elseif(preg_match('#^add([[:alnum:]]{1,})$#', $method, $matches)
            && count($arguments) == 1
            && intval($arguments[0]) != 0
        ){//For ReferencedInTable
            $element = $matches[1];
            $attribut = lcfirst($element).'s';

            if(is_array($this->$attribut)){
                //\Doctrine\Common\Util\Debug::dump($arguments[0]->getOid());
                $tableau = &$this->$attribut;

                $tableau[] = $arguments[0];
            }
            return $this;
        }else{
            throw new ElementNotFoundException('Element not existing in database : '.$method);
        }
    }
    
    public function getOid()
    {
        return $this->oid;
    }

    public function setOid($oid)
    {
        $this->oid = $oid;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setOwner($owner)
    {
        $this->owner = $owner;
    }

}
