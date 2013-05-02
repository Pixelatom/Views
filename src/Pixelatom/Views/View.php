<?php
namespace Pixelatom\Views;


/**
 * Main View class of MVC pattern .
 * 
 * Views are files that contain the display information for your application. This is most commonly HTML, CSS and JavaScript but can be anything you require such as XML or Json for AJAX output. The purpose of views is to keep this information separate from your application logic for easy reusability and cleaner code.
 * While this is true, views themselves can contain code used for displaying the data you pass into them. For example, looping through an array of product information and display each one on a new table row. Views are still PHP files so you can use any code you normally would. Views are executed in the Controller namespace so you have access to all resources you have loaded into $this->
 * When this view is rendered it is executed just as any PHP script would and the output from it is returned (or sent to the browser if you so wish).
 *
 */
class View implements Countable , Iterator , Traversable ,  ArrayAccess {
    
    
    /**
     * Factory method for View class
     */
    public static function create() {
        $class = get_called_class();
        $args = func_get_args();
        $r_class = new \ReflectionClass($class);
        return $r_class->newInstanceArgs($args);
    }
    
    
    // Views     
    protected $_default_view;
    protected $_custom_path = false;
    static protected $_paths = array();
    // set to true if the view being rendered is inside another view
    static protected $_status_rendering_child_view = false;
    
    function __construct($default_view = null, $custom_path = null) {
        $this->set_default_view($default_view);
        $this->set_custom_path($custom_path);
    }

    /**
     * set() can be used to set a variable in a view	 
     */
    public function set($varname, $value) {
        $this->array[$varname] = $value;
        return $this;
    }

    /**
     * bind() is like set only the variable is assigned by reference.
     */
    public function bind($varname, &$value) {
        $this->array[$varname] = &$value;
        return $this;
    }

    /**
     * return true if the var name is set for this view
     */
    public function key_exists($key) {
        return isset($this->array[$key]);        
    }

    /**
     * get the value set for a view variable
     */
    public function get($key) {
        if (!isset($this->array[$key]))
            throw new Exception("Value for key '$key' doesn't exists on View Object.");
        return $this->array[$key];
    }

    /**
     * remove a view variable by name
     * 
     */
    public function remove($key) {
        if (!isset($this->array[$key]))
            return $this;
        unset($this->array[$key]);
        return $this;
    }

    /**
     * remove all the variables set for this view
     */
    public function clear() {
        $this->array = array();
        return $this;
    }

    public function __toString() {
        ob_start();
        try {
            $this->show();
        } catch (Exception $e) {
            ob_end_clean();
            if (ini_get('display_errors'))
                return $e->__toString();
            else
                throw $e;
        }

        $return = ob_get_contents();
        ob_end_clean();
        return $return;
    }
    

    /**
     * adds a path where the view can be searched for
     * the last path added is the first where the view will be searched in
     * if the view is found, it'll be used, so it means you ca design a cascade filsystem where views can be optionally overriden
     */
    static public function add_path($path) {
        array_unshift(self::$_paths, $path);
    }

    static public function clear_paths() {
        self::$_paths = array();
    }

    static public function remove_path($path) {
        $index = array_search($path, self::$_paths, true);
        if ($index !== false) {
            unset(self::$_paths[$index]);
            return true;
        }
        return false;
    }

    
    

    /**
     * change the path where the class will search for the file it has to show
     */
    public function set_custom_path($custom_path) {
        $this->_custom_path = $custom_path;
        return $this;
    }

    /**
     * set a default file or object to render when the method show is called without parameters
     */
    public function set_default_view($default_view) {
        $this->_default_view = $default_view;
        return $this;
    }

    

    /**
     * renders the output of the View.
     *
     * @param unknown_type $name: (opcional) si no es especificado
     * muestra el view default del objeto,
     * si es un strig, busca el archivo .php con el mismo nombre y lo usa
     * de view.
     * si es otro objeto view, lo muestra agregandole las variables
     * que tiene seteadas este objeto.
     *
     */
    function show($name = null) {
        //Event::run('view.show_call', $this, $name);

        $restore_rendering_status = false;

        if (!self::$_status_rendering_child_view) {
            // this is the 'parent' view
            self::$_status_rendering_child_view = true;
            $restore_rendering_status = true;
        }

        $onbuffer = false;
        $onbuffer = ob_start();
        try {
            // si $name se trata de un objeto
            if (!empty($name) and is_object($name) and (get_class($name) == '/Pixelatom/Views/View' or is_subclass_of($name, '/Pixelatom/Views/View'))) {
                /* @var $name View */
                $name = clone $name;

                # copiamos todas las variables que tenemos en este view al view que se paso por parametros
                foreach ($this->array as $key => &$value) {
                    if (!$name->key_exists($key)) {
                        $name->bind($key,$value);
                    }
                }

                $name->show();
            } 
            else{

                #buscamos entre todas las variables que tenemos asignadas por un objeto view
                foreach ($this->array as $key => &$value) {
                    # si es un view le asigna las variables que este view tiene
                    if (!empty($value) and is_object($value) and (get_class($value) == '/Pixelatom/Views/View' or is_subclass_of($value, '/Pixelatom/Views/View'))) {
                        $value = clone $value;

                        foreach ($this->array as $key1 => &$value1) {
                            if (!$value->key_exists($key1)) {
                                $value->bind($key1,$value1);
                            }
                        }
                    }
                }

                $this->include_view($name, $this->array);
            }
        }
        catch (\Exception $e) {
            if ($onbuffer) {
                ob_end_flush();
            }
            throw $e;
        }
        
        if ($onbuffer) {
            $output = ob_get_contents();
            ob_end_clean();

            //Event::run('view.show', $output, $this, $name, $is_parent_view = $restore_rendering_status);
            echo $output;
        }
        
        if ($restore_rendering_status == true) self::$_status_rendering_child_view = false;


        return $this;
    }
    
    protected function include_view($name, &$this->array) {
        //// self::$_paths
        # va a mostrar view default
        if (empty($name)) {
            if (empty($this->_default_view)) throw new Exception("Empty view");
            $name = $this->_default_view;
        }

        $path = '';

        if (empty($this->_custom_path)) {
            foreach (self::$_paths as $dir) {
                if (file_exists($dir . '/' . $name . '.php')) {
                    $path = $dir . '/' . $name . '.php';
                    break;
                }
            }
        }
        else
            $path = realpath($this->_custom_path) . '/' . $name . '.php';

        if (!file_exists($path))
            throw new \Exception('View `' . $name . '` does not exists or can not be found');


        $this->clousure($path, $this->array);
    }

    protected function clousure($path, &$this->array) {
        extract($this->array, EXTR_REFS);
        include($path);
    }
    
    // interfaces implementations
    
    protected $position = 0;
    protected $array = array();
    
    // properties setters and getters
    public function __set($name, $value)
    {   
        $this->array[$name] = $value;
    }

    public function &__get($name)
    {
        if (array_key_exists($name, $this->array)) {
            return $this->array[$name];
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
     
        return null;
    }
    
    public function __isset($name)
    {
        return isset($this->array[$name]);
    }

    public function __unset($name)
    {
        unset($this->array[$name]);
    }
    
    // countable methods
    public function count() 
    { 
        return count($this->array); 
    }
    
    
    // iterable methods
    public function rewind() {
    
        $this->position = 0;
    }

    public function current() {
    
        return $this->array[$this->position];
    }

    public function key() {
    
        return $this->position;
    }

    public function next() {
    
        ++$this->position;
    }

    public function valid() {
    
        return isset($this->array[$this->position]);
    }
    
    // array access methods
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->array[] = $value;
        } else {
            $this->array[$offset] = $value;
        }
    }
    public function offsetExists($offset) {
        return isset($this->array[$offset]);
    }
    public function offsetUnset($offset) {
        unset($this->array[$offset]);
    }
    public function offsetGet($offset) {
        return isset($this->array[$offset]) ? $this->array[$offset] : null;
    }

}