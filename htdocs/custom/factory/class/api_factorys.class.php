<?php
/* Copyright (C) 2015		Jean-François Ferry		<jfefe@aternatik.fr>
 * Copyright (C) 2016		Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2019		Charlene Benke			<eldy@users.sourceforge.net>

 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

 use Luracast\Restler\RestException;

dol_include_once('/factory/class/factory.class.php');

/**
 * API class for factorys
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Factorys extends DolibarrApi
{

    /**
     * @var array   $FIELDS     Mandatory fields, checked when create and update object
     */
    static $FIELDS = array(
      'rowid',
      'fk_product',
      'description',
    );

    /**
     * @var array   $FIELDS     Mandatory fields, checked when create and update object
     */
    static $FIELDSLINE = array(
      'description',
      'date',
      'duree',
    );

    /**
     * @var factory $factory {@type Factory}
     */
    public $factory;

    /**
     * Constructor
     */
    function __construct()
    {
		global $db, $conf;
		$this->db = $db;
        $this->factory = new Factory($this->db);
    }

    /**
     * Get properties of a Factory object
     *
     * Return an array with Expense Report informations
     *
     * @param       int         $id         ID of Expense Report
     * @return 	    array|mixed             Data without useless information
     *
     * @throws 	RestException
     */
    function get($id)
    {
    	if(! DolibarrApiAccess::$user->rights->factory->lire) {
    		throw new RestException(401);
    	}

    	$result = $this->factory->fetch($id);
    	if( ! $result ) {
    		throw new RestException(404, 'FActory report not found');
    	}

    	if( ! DolibarrApi::_checkAccessToResource('factory',$this->factory->id)) {
    		throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
    	}

    	$this->factory->fetchObjectLinked();
    	return $this->_cleanObjectDatas($this->factory);
    }

    /**
     * List of Factory
     *
     * Return a list of Factory
     *
     * @param string	       $sortfield	        Sort field
     * @param string	       $sortorder	        Sort order
     * @param int	       $limit		        Limit for list
     * @param int	       $page		        Page number
     * @param string   	       $thirdparty_ids	        Thirdparty ids to filter orders of. {@example '1' or '1,2,3'} {@pattern /^[0-9,]*$/i}
     * @param string           $sqlfilters              Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
     * @return  array                                   Array of order objects
     *
     * @throws RestException
     */
    function index($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $product_ids = '', $sqlfilters = '')
    {
        global $db, $conf;

        $obj_ret = array();

        // case of external user, $thirdparty_ids param is ignored and replaced by user's socid
        //        $socids = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : $thirdparty_ids;

        // If the internal user must only see his customers, force searching by him
        //        $search_sale = 0;
        //        if (! DolibarrApiAccess::$user->rights->societe->client->voir && !$socids) $search_sale = DolibarrApiAccess::$user->id;

        $sql = "SELECT t.rowid";

        $sql.= " FROM ".MAIN_DB_PREFIX."factory as t";

        $sql.= ' WHERE t.entity IN ('.getEntity('factory').')';
        // Insert sale filter
        if ($product_ids > 0)
        {
            $sql .= " AND t.fk_product= ".$product_ids;
        }
        // Add sql filters
        if ($sqlfilters)
        {
            if (! DolibarrApi::_checkFilters($sqlfilters))
            {
                throw new RestException(503, 'Error when validating parameter sqlfilters '.$sqlfilters);
            }
	        $regexstring='\(([^:\'\(\)]+:[^:\'\(\)]+:[^:\(\)]+)\)';
            $sql.=" AND (".preg_replace_callback('/'.$regexstring.'/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters).")";
        }

        $sql.= $db->order($sortfield, $sortorder);
        if ($limit)	{
            if ($page < 0)
            {
                $page = 0;
            }
            $offset = $limit * $page;

            $sql.= $db->plimit($limit + 1, $offset);
        }

        dol_syslog("API Rest request");
        $result = $db->query($sql);

        if ($result)
        {
            $num = $db->num_rows($result);
            $min = min($num, ($limit <= 0 ? $num : $limit));
            $i = 0;
            while ($i < $min)
            {
                $obj = $db->fetch_object($result);
                $factory_static = new factory($db);
                if($factory_static->fetch($obj->rowid)) {
                	$obj_ret[] = $this->_cleanObjectDatas($factory_static);
                }
                $i++;
            }
        }
        else {
            throw new RestException(503, 'Error when retrieve factory list : '.$db->lasterror());
        }
        if( ! count($obj_ret)) {
            throw new RestException(404, 'No factory found');
        }
		return $obj_ret;
    }

    /**
     * Create Factory object
     *
     * @param   array   $request_data   Request data
     * @return  int     ID of intervention
     */
    function post($request_data = null)
    {
      if(! DolibarrApiAccess::$user->rights->factory->creer) {
			  throw new RestException(401, "Insuffisant rights");
		  }
        // Check mandatory fields
        $result = $this->_validate($request_data);
        foreach($request_data as $field => $value) {
            $this->factory->$field = $value;
        }

        if ($this->factory->create(DolibarrApiAccess::$user) < 0) {
            throw new RestException(500, "Error creating factory", array_merge(array($this->factory->error), $this->factory->errors));
        }

        return $this->factory->id;
    }


    /**
     * Delete factory
     *
     * @param   int     $id         Order ID
     * @return  array
     */
    function delete($id)
    {
    	if(! DolibarrApiAccess::$user->rights->ficheinter->supprimer) {
    		throw new RestException(401);
    	}
    	$result = $this->factory->fetch($id);
    	if( ! $result ) {
    		throw new RestException(404, 'Intervention not found');
    	}

    	if( ! DolibarrApi::_checkAccessToResource('commande',$this->factory->id)) {
    		throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
    	}

    	if( ! $this->factory->delete(DolibarrApiAccess::$user)) {
    		throw new RestException(500, 'Error when delete OF : '.$this->factory->error);
    	}

    	return array(
	    	'success' => array(
		    	'code' => 200,
		    	'message' => 'Factory deleted'
	    	)
    	);
    }

    /**
     * Validate an intervention
     *
     * If you get a bad value for param notrigger check, provide this in body
     * {
     *   "notrigger": 0
     * }
     *
     * @param   int $id             Intervention ID
     * @param   int $notrigger      1=Does not execute triggers, 0= execute triggers
     *
     * @url POST    {id}/validate
     *
     * @return  array
     */
    function validate($id, $notrigger=0)
    {
        if(! DolibarrApiAccess::$user->rights->factory->creer) {
                          throw new RestException(401, "Insuffisant rights");
                  }
        $result = $this->factory->fetch($id);
        if( ! $result ) {
            throw new RestException(404, 'Intervention not found');
        }

        if( ! DolibarrApi::_checkAccessToResource('factory',$this->factory->id)) {
            throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
        }

        $result = $this->factory->setValid(DolibarrApiAccess::$user, $notrigger);
        if ($result == 0) {
        	throw new RestException(304, 'Error nothing done. May be object is already validated');
        }
        if ($result < 0) {
        	throw new RestException(500, 'Error when validating Factory: '.$this->factory->error);
        }

        $this->factory->fetchObjectLinked();

        return $this->_cleanObjectDatas($this->factory);
    }

    /**
     * Close an OF
     *
     * @param   int 	$id             OF ID
     *
     * @url POST    {id}/close
     *
     * @return  array
     */
    function closefactory($id)
    {
        if(! DolibarrApiAccess::$user->rights->factory->creer)
        {
            throw new RestException(401, "Insuffisant rights");
        }
        $result = $this->factory->fetch($id);
        if (! $result) {
            throw new RestException(404, 'Intervention not found');
        }

        if (! DolibarrApi::_checkAccessToResource('factory',$this->factory->id)) {
            throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
        }

        $result = $this->factory->setStatut(3);

        if ($result == 0) {
        	throw new RestException(304, 'Error nothing done. May be object is already closed');
        }
        if ($result < 0) {
        	throw new RestException(500, 'Error when closing Factory: '.$this->factory->error);
        }

        $this->factory->fetchObjectLinked();

        return $this->_cleanObjectDatas($this->factory);
    }

    /**
     * Validate fields before create or update object
     *
     * @param array $data   Data to validate
     * @return array
     *
     * @throws RestException
     */
    function _validate($data)
    {
        $factory = array();
        foreach (Factory::$FIELDS as $field) {
            if (!isset($data[$field]))
                throw new RestException(400, "$field field missing");
            $factory[$field] = $data[$field];
        }
        return $factory;
    }


    /**
     * Clean sensible object datas
     *
     * @param   object  $object    Object to clean
     * @return    array    Array of cleaned object properties
     */
    function _cleanObjectDatas($object)
    {

    	$object = parent::_cleanObjectDatas($object);

    	unset($object->statuts_short);
    	unset($object->statuts_logo);
    	unset($object->statuts);

    	return $object;
    }

    /**
     * Validate fields before create or update object
     *
     * @param array $data   Data to validate
     * @return array
     *
     * @throws RestException
     */
    function _validateLine($data)
    {
        $factory = array();
        foreach (Factory::$FIELDSLINE as $field) {
            if (!isset($data[$field]))
                throw new RestException(400, "$field field missing");
            $factory[$field] = $data[$field];
        }
        return $factory;
    }
}
