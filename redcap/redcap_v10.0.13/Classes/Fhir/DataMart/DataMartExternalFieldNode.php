<?php


namespace Vanderbilt\REDCap\Classes\Fhir\DataMart
{
    /**
     * object used to populate the tree of the external fields
     */
    class DataMartExternalFieldNode implements \JsonSerializable {

        private $attributes = array();
        public $name;
        private $children= array();
        public $parent = null; // reference to the parent node

        function __construct($name='untitled', $params=array())
        {
            $this->name = $name;
            foreach ($params as $key => $value) {
                $this->{$key} = $value;
            }
        }

        /**
         * add child node to a node
         *
         * @param DataMartExternalFieldNode $node
         * @param array $containers
         * @return void
         */
        function addChild($node, $containers=array())
        {
            $current = $this;
            foreach ($containers as $container) {
                if(!$current->children[$container]) {
                    // recursively add new node if container not present 
                    $node_container = new self($container);
                    $current->addChild($node_container);
                }
                $current = $current->children[$container];
            }
            $node->parent = $current;
            $current->children[$node->name] = $node;
        }

        /**
         * check if the node is a container
         */
        public function isContainer()
        {
            return empty($this->attributes);
        }

        /**
         * setter
         */
        public function __set($name, $value)
        {
            $this->attributes[$name] = utf8_encode($value);
        }

        /**
         * magic getters
         */
        public function __get($name)
        {
            if (array_key_exists($name, $this->attributes)) {
                return $this->attributes[$name];
            }

            $trace = debug_backtrace();
            trigger_error(
                'Undefined property via __get(): ' . $name .
                ' in ' . $trace[0]['file'] .
                ' on line ' . $trace[0]['line'],
                E_USER_NOTICE);
            return null;
        }

        public function getTotal()
        {
            $children = $this->children;
            $total = count($children);
            return $total;
        }

        /**
         * recursively count the children of a node
         *
         * @param DataMartExternalFieldNode $node
         * @return int
         */
        public function getSubtotal()
        {
            $children = $this->children;
            $total = $this->getTotal();
            if($total>0)
            {
                foreach ($children as $key => $child) {
                    $total += $child->getSubtotal();
                }
            }
            return $total;
        }


        /**
         * count the fields of a
         *
         * @return void
         */
        public function getFieldsTotal()
        {
            $total = 0;
            foreach ($this->children as $key => $child) {
                if(!$child->isContainer()) $total++;
            }
            return $total;
        }

        /**
         * recursively count the fields of a node
         *
         * @return int
         */
        public function getFieldsSubtotal()
        {
            $total = $this->getFieldsTotal();
            foreach ($this->children as $key => $child) {
                $total += $child->getFieldsSubtotal();
            }
            return $total;
        }
        
        /**
         * Returns data which can be serialized
         *
         * @return array
         */
        public function jsonSerialize()
        {
            $metadata = array();
            $metadata['total'] = $this->getTotal();
            $metadata['subtotal'] = $this->getSubtotal();
            $metadata['fieldsTotal'] = $this->getFieldsTotal();
            $metadata['fieldsSubtotal'] = $this->getFieldsSubtotal();
            $serialized = array(
                'parent' => $this->parent->name ?: '#',
                'name' => $this->name,
                'data' => $this->children,
                'attributes' => (object) $this->attributes,
                'metadata' => $metadata,
                'isContainer' => $this->isContainer(),
            );
            return $serialized;
        }
    }
}