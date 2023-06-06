<?php
/* Copyright (C)	2013-2022		Charlene BENKE		<charlene@patas-monkey.com>
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

/**
 *	\defgroup   factory	 Module gestion de la fabrication
 *	\brief	  Module pour gerer les process de fabrication
 *	\file	   htdocs/factory/core/modules/modFactory.class.php
 *	\ingroup	factory
 *	\brief	  Fichier de description et activation du module factory
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


/**
 *	Classe de description et activation du module Propale
 */
class modfactory extends DolibarrModules
{

	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param	  DoliDB		$db	  Database handler
	 */
	function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->numero = 160310;

		$this->family = "products";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' )
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Gestion de la fabrication";

		$this->editor_name = "<b>Patas-Monkey</b>";
		$this->editor_web = "http://www.patas-monkey.com";

		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = $this->getLocalVersion();

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->special = 0;
		$this->picto=$this->name.'@'.$this->name;

		// Data directories to create when module is enabled
		$this->dirs = array("/".$this->name."/temp");

		// Constantes
		$this->const = array(
			0 => array(
				'FACTORY_ADDON', 'chaine', 'mod_mandril',
				'Numbering Factory rule', 1, 'allentities', 1
			),
			1 => array(
				'FACTORY_ADDON_PDF', 'chaine', 'capucin', 
				'default pdf', 1, 'allentities', 1
			)
		);

		// Dependancies
		$this->depends = array();
		$this->requiredby = array(); // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array(); // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->config_page_url = array($this->name.".php@".$this->name);
		$this->langfiles = array("propal", "order", "project", "companies", "products", "factory@factory");

		$this->need_dolibarr_version = array(3, 4);

		// hook pour la recherche
		$this->module_parts = array(
			'hooks' => array('searchform', 'element_resource', 'productdao', 'mrpindex'),
			'tpllinkable' => 1,
			'contactelement' => 1,
			'substitutions' => 1,
			'models' => 1
		);

		
		// Boites
		$this->boxes = array();
		$r=0;
		$this->boxes[$r][1] = "box_factory.php@".$this->name;

		// Constants
		$this->const = array();
		$r=0;

		$this->const[$r][0] = "FACTORY_ADDON_PDF";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "capucin";
		$this->const[$r][4] = 1;
		$r++;

		$this->const[$r][0] = "FACTORY_ADDON";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "mod_mandrill";
		$this->const[$r][4] = 1;


		// contact element setting
		$this->contactelement=1;

		// Permissions
		$this->rights = array();
		$this->rights_class = $this->name;
		$r=0;

		$r++;
		$this->rights[$r][0] = 160310; // id de la permission
		$this->rights[$r][1] = 'Lire les fabrications'; // libelle de la permission
		$this->rights[$r][2] = 'r'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'lire';
		$r++;
		$this->rights[$r][0] = 160311; // id de la permission
		$this->rights[$r][1] = 'cr&eacute;er une fabrication'; // libelle de la permission
		$this->rights[$r][2] = 'c'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'creer';
		$r++;
		$this->rights[$r][0] = 160312; // id de la permission
		$this->rights[$r][1] = 'Annuler la fabrication'; // libelle de la permission
		$this->rights[$r][2] = 'd'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'annuler';

		$r++;
		$this->rights[$r][0] = 160313; // id de la permission
		$this->rights[$r][1] = 'Envoyer par mail un Ordre de Fabrication'; // libelle de la permission
		$this->rights[$r][2] = 'e'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 0; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'send';
		$r++;
		$this->rights[$r][0] = 160314; // id de la permission
		$this->rights[$r][1] = "voir la tarification d'un ordre de fabrication"; // libelle de la permission
		$this->rights[$r][2] = 'p'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 0; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'showprice';
		$r++;
		$this->rights[$r][0] = 160315; // id de la permission
		$this->rights[$r][1] = 'Exporter les fabrication'; // libelle de la permission
		$this->rights[$r][2] = 'x'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 0; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'export';

		$r++;
		$this->rights[$r][0] = 160316; // id de la permission
		$this->rights[$r][1] = 'Mise a jour de la description'; // libelle de la permission
		$this->rights[$r][2] = 'u'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 0; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'update';

		$r++;
		$this->rights[$r][0] = 160317; // id de la permission
		$this->rights[$r][1] = 'supprimer un OF'; // libelle de la permission
		$this->rights[$r][2] = 'u'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 0; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'supprimer';

		$r++;
		$this->rights[$r][0] = 160318; // id de la permission
		$this->rights[$r][1] = 'cloturer un OF'; // libelle de la permission
		$this->rights[$r][2] = 'u'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 0; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'cloturer';


		// si on est en V11, utilisation du nouveau menu BOM
		if ((int) DOL_VERSION > 10 && property_exists($conf->global, "MAIN_MODULE_BOM")) 
			$mainmenu="mrp";
		else
			$mainmenu="products";

		// factory Menu
		$r=0;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu='.$mainmenu,
					'type'=>'left',	
					'titre'=>'Factory',
					'mainmenu'=>$mainmenu,
					'leftmenu'=>'factory',
					'url'=>'/factory/index.php?leftmenu=factory',
					'langs'=>'factory@factory',
					'prefix'=>  img_picto('', 'object_factory@factory', 'style="padding-right: 10px"'),
					'position'=>110, 'enabled'=>'1',
					'perms'=>'1',
					'target'=>'', 'user'=>2);
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu='.$mainmenu.',fk_leftmenu=factory',
					'type'=>'left',
					'titre'=>'CreateOFShort',
					'mainmenu'=>'', 'leftmenu'=>'',
					'url'=>'/factory/fiche.php',
					'langs'=>'factory@factory',
					'position'=>110, 'enabled'=>'1',
					'perms'=>'1', 'target'=>'',
					'user'=>2);
		$r++;

		if ((int) DOL_VERSION >= 6) {
			$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu='.$mainmenu.',fk_leftmenu=factory',
						'type'=>'left',
						'titre'=>'List',
						'mainmenu'=>'', 'leftmenu'=>'',
						'url'=>'/factory/list.php',
						'langs'=>'factory@factory',
						'position'=>110, 'enabled'=>'1',
						'perms'=>'1',
						'target'=>'', 'user'=>2);
		} else {
			$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu='.$mainmenu.',fk_leftmenu=factory',
						'type'=>'left',
						'titre'=>'List',
						'mainmenu'=>'', 'leftmenu'=>'',
						'url'=>'/factory/list-old.php',
						'langs'=>'factory@factory',
						'position'=>110, 'enabled'=>'1',
						'perms'=>'1',
						'target'=>'', 'user'=>2);
		}
		$r++;
		
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu='.$mainmenu.',fk_leftmenu=factory',
					'type'=>'left',
					'titre'=>'Declinaison',
					'mainmenu'=>'', 'leftmenu'=>'',
					'url'=>'/factory/declinaison.php',
					'langs'=>'factory@factory',
					'position'=>110, 'enabled'=>'1',
					'perms'=>'1', 'target'=>'',
					'user'=>2);
		$r++;

        $rightParams = '';
        if ((int) DOL_VERSION >= 17) {
            $rightParams = ":\$user->rights->factory->lire";
        }

		// additional tabs
		$this->tabs = array(
			  'product:+factory:SUBSTITUTION_factorynbproduct:@Produit'.$rightParams.':/factory/product/index.php?id=__ID__'
			, 'project:+factory:ProductNeed:@project'.$rightParams.':/factory/project/productinproject.php?id=__ID__'
			, 'task:+factory:Factory:@tasks'.$rightParams.':/factory/project/factorytask.php?id=__ID__&withproject=1'
			, 'stock:+contact:Contact:@stock'.$rightParams.':/factory/product/stock/contact.php?id=__ID__'
			, 'stock:+factory:Factory:@stock'.$rightParams.':/factory/product/stock/list.php?id=__ID__'
			, 'order:+factory:SUBSTITUTION_factorynborder:@order'.$rightParams.':/factory/tabs/factoryorder.php?id=__ID__'
			, 'propal:+factory:SUBSTITUTION_factorynbpropal:@propal'.$rightParams.':/factory/tabs/factorypropal.php?id=__ID__'
			, 'bom:+factory:SUBSTITUTION_factorynbbom:@bom'.$rightParams.':/factory/tabs/factorybom.php?id=__ID__'
		);


		// Exports
		//--------
		$r=1;
		$this->export_code[$r]=$this->rights_class.'_'.$r;
		$this->export_label[$r]='FactoryComponent';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_permission[$r]=array(array("factory", "export"));
		$this->export_fields_array[$r]=array(
						'pf.fk_product_father'=>"RefProductMade",'pf.fk_product_children'=>"RefProductComponent",
						'pf.pmp'=>"FactoryPmpComponent",'pf.price'=>"FactoryPriceComponent",'pf.qty'=>"FactoryQtyComponent",
						'pf.globalqty'=>"GlobalQtyComponent",'pf.description'=>"FactoryComponentDesc"
		);
	
		/// type de champs possible
		// Text / Numeric / Date / List:NomTable:ChampLib / Duree  / Boolean	
		$this->export_TypeFields_array[$r]=array(
						'pf.fk_product_father'=>"List:product:label",'pf.fk_product_children'=>"List:product:label",
						'pf.pmp'=>"Number",'pf.price'=>"Number",'pf.qty'=>"Number",'pf.globalqty'=>"Number",'pf.description'=>"Text"
		);

		$this->export_entities_array[$r]=array('pf.fk_product_father'=>"product",'pf.fk_product_children'=>"product",
		'pf.pmp'=>"factory@factory:Factory",'pf.price'=>"factory@factory:Factory",'pf.qty'=>"factory@factory:Factory",
		'pf.globalqty'=>"factory@factory:Factory",'pf.description'=>"factory@factory:Factory");
		
		$this->export_sql_start[$r]='SELECT DISTINCT ';
		$this->export_sql_end[$r]  =' FROM '.MAIN_DB_PREFIX.'product_factory as pf ';


		$r=2;
		$this->export_code[$r]=$this->rights_class.'_'.$r;
		$this->export_label[$r]='FactoryList';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_permission[$r]=array(array("factory","export"));
		$this->export_fields_array[$r]=array('f.rowid'=>"FactoryId",'f.ref'=>"FactoryRef",'f.fk_product'=>"RefProduit",
		'f.date_start_planned'=>"FactoryDateStartPlanned",'f.date_end_planned'=>"FactoryDateEndPlanned",
		'f.qty_planned'=>"FactoryQtyPlanned",'f.duration_planned'=>"FactoryDurationPlanned",
		'f.date_start_made'=>"FactoryDateStartMade",'f.date_end_made'=>"FactoryDateEndMade",'f.qty_made'=>"FactoryQtyMade",
		'f.duration_made'=>"FactoryDurationMade",
		'f.fk_statut'=>'FactoryStatus','f.description'=>"FactoryDescr",'f.fk_entrepot'=>"FactoryEntrepot",
		'bb.ref'=>"RefBOM",
		'fd.rowid'=>'FactorydetId','fd.fk_product'=>"FactoryComponent",'fd.description'=>"FactoryLineDesc",
		'fd.qty_unit'=>"QtyUnit",'fd.qty_planned'=>"QtyPlanned",'fd.qty_used'=>"QtyUsed",'fd.qty_deleted'=>"QtyDeleted",
		'fd.pmp'=>"FactoryPmpComponent",'fd.price'=>"FactoryPriceComponent"
		);
	
		/// type de champs possible
		// Text / Numeric / Date / List:NomTable:ChampLib / Duree  / Boolean
		
		if ((int) DOL_VERSION < 7)
			$entrepotlabel= " label";
		else
			$entrepotlabel= " ref";
	
		$this->export_TypeFields_array[$r]=array('f.ref'=>"Text",'f.fk_product'=>"List:product:label",
		'f.date_start_planned'=>"Date",'f.date_end_planned'=>"Date",'f.qty_planned'=>"Number",'f.duration_planned'=>"Number",
		'f.date_start_made'=>"Date",'f.date_end_made'=>"Date",'f.qty_made'=>"Number",'f.duration_made'=>"Number",
		'f.fk_statut'=>'Statut','f.description'=>"Text",'f.fk_entrepot'=>"List:entrepot:".$entrepotlabel,
		'bb.ref'=>"Text",
		'fd.fk_product'=>"List:product:label",'fd.description'=>"Text",
		'fd.qty_unit'=>"Number",'fd.qty_planned'=>"Number",'fd.qty_used'=>"Number",'fd.qty_deleted'=>"Number",
		'fd.pmp'=>"Number",'fd.price'=>"Number"
		);
		
		$this->export_entities_array[$r]=array('f.rowid'=>"factory@factory:Factory",'f.ref'=>"factory@factory:Factory",'f.fk_product'=>"product",
		'f.date_start_planned'=>"factory@factory:Factory",'f.date_end_planned'=>"factory@factory:Factory",
		'f.qty_planned'=>"factory@factory:Factory",'f.duration_planned'=>"factory@factory:Factory",
		'f.date_start_made'=>"factory@factory:Factory",'f.date_end_made'=>"factory@factory:Factory",
		'f.qty_made'=>"factory@factory:Factory",'f.duration_made'=>"factory@factory:Factory",
		'bb.ref'=>"bom",
		'f.fk_statut'=>'factory@factory:Factory','f.description'=>"factory@factory:Factory",'f.fk_entrepot'=>"stock",
		'fd.rowid'=>'factory@factory:Factory','fd.fk_product'=>"product",'fd.description'=>"factory@factory:Factory",
		'fd.qty_unit'=>"factory@factory:Factory",'fd.qty_planned'=>"factory@factory:Factory",
		'fd.qty_used'=>"factory@factory:Factory",'fd.qty_deleted'=>"factory@factory:Factory",
		'fd.pmp'=>"factory@factory:Factory",'fd.price'=>"factory@factory:Factory"
		);

		$keyforselect='factory'; $keyforelement='factory@factory:Factory'; $keyforaliasextra='fe';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';

		$this->export_sql_start[$r]='SELECT DISTINCT ';
		$this->export_sql_end[$r]  =' FROM '.MAIN_DB_PREFIX.'factory as f ';
		$this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'factory_extrafields as fe ON f.rowid = fe.fk_object';
		$this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'factorydet as fd ON (f.rowid = fd.fk_factory)';
		$this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'bom_bom as bb ON (f.fk_bom = bb.rowid)';

		$this->export_sql_end[$r] .=' WHERE f.entity = '.$conf->entity;


		$r=1;
		$this->import_code[$r]=$this->rights_class.'_'.$r;
		$this->import_label[$r]="FactoryComponents";	// Translation key
		$this->import_icon[$r]=$this->picto;
		$this->import_entities_array[$r]=array('pf.fk_product_father'=>"product",'pf.fk_product_children'=>"product");		
		$this->import_tables_array[$r]=array('pf'=>MAIN_DB_PREFIX.'product_factory');
		//$this->import_tables_creator_array[$r]=array('e'=>'fk_user_author');	// Fields to store import user id
		$this->import_fields_array[$r]=array(	
			'pf.fk_product_father'=>"ProductIDFactory*",
			'pf.fk_product_children'=>"ProductIDComponent*",
			'pf.pmp'=>"FactoryPmpComponent",
			'pf.price'=>"FactoryPriceComponent",
			'pf.qty'=>"FactoryQtyComponent*",
			'pf.globalqty'=>"GlobalQtyComponent",
			'pf.description'=>"FactoryComponentDesc"
		);

		$this->import_convertvalue_array[$r]=array(
						'pf.fk_product_father'=>array(
										'rule'=>'fetchidfromref','classfile'=>'/product/class/product.class.php',
										'class'=>'Product','method'=>'fetch','element'=>'product'
						),
						'pf.fk_product_children'=>array(
										'rule'=>'fetchidfromref','classfile'=>'/product/class/product.class.php',
										'class'=>'Product','method'=>'fetch','element'=>'product'
						),
		);

		$this->import_examplevalues_array[$r]=array(
			'pf.fk_product_father'=>"ProdFather",
			'pf.fk_product_children'=>"ProdComponent",
			'pf.pmp'=>"1",
			'pf.price'=>"2",
			'pf.qty'=>"1",
			'pf.globalqty'=>"0",
			'pf.description'=>"Description"
		);

	}


	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
	 *	  @param	  string	$options	Options when enabling module ('', 'noboxes')
	 *	  @return	 int			 	1 if OK, 0 if KO
	 */
	function init($options='')
	{
		global $conf;

		// Permissions
		$this->remove($options);

		$sql = array();
		
		$result=$this->load_tables();

		return $this->_init($sql, $options);
	}

	/**
	 *		Function called when module is disabled.
	 *	  Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted
	 *
	 *	  @param	  string	$options	Options when enabling module ('', 'noboxes')
	 *	  @return	 int			 	1 if OK, 0 if KO
	 */
	function remove($options='')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
	
	/**
	 *		Create tables, keys and data required by module
	 * 		Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
	 * 		and create data commands must be stored in directory /mymodule/sql/
	 *		This function is called by this->init.
	 *
	 * 		@return		int		<=0 if KO, >0 if OK
	 */
	function load_tables()
	{
		return $this->_load_tables('/factory/sql/');
	}

	function getChangeLog()
	{
		// Libraries
		dol_include_once("/".$this->name."/core/lib/patasmonkey.lib.php");
		return getChangeLog($this->name);
	}

	function getVersion($translated = 1)
	{
		global $langs, $conf;
		$currentversion = $this->version;

		if (!empty($conf->global->PATASMONKEY_SKIP_CHECKVERSION) && $conf->global->PATASMONKEY_SKIP_CHECKVERSION == 1)
			return $currentversion;

		if ($this->disabled) {
			$newversion= $langs->trans("DolibarrMinVersionRequiered")." : ".$this->dolibarrminversion;
			$currentversion="<font color=red><b>".img_error($newversion).$currentversion."</b></font>";
			return $currentversion;
		}

		$context  = stream_context_create(array('http' => array(
			'user_agent' => 'Mozilla/4.0 (compatible; MSIE 6.0)',
			'header' => 'Accept: application/xml')
		));
		$changelog = file_get_contents(
						str_replace("www", "dlbdemo", $this->editor_web).'/htdocs/custom/'.$this->name.'/changelog.xml',
						false, $context
		);
		//$changelog = @file_get_contents($this->editor_web.$this->editor_version_folder.$this->name.'/');

		if ($changelog === false)
			return $currentversion;	// not connected
		else {
			$sxelast = simplexml_load_string(nl2br($changelog));
			if ($sxelast === false)
				return $currentversion;
			else
				$tblversionslast=$sxelast->Version;

			$lastversion = $tblversionslast[count($tblversionslast)-1]->attributes()->Number;
			$this->lastVersion = $lastversion;

			if ($lastversion != (string) $this->version) {
				if ($lastversion > (string) $this->version) {
					$this->needUpdate =true;
					$newversion= $langs->trans("NewVersionAviable")." : ".$lastversion;
					$currentversion="<font title='".$newversion."' color=orange><b>".$currentversion."</b></font>";
				} else // version pilote
					$currentversion="<font title='Version Pilote' color=blue><b>".$currentversion."</b></font>";
			}
			else
				return $lastversion ."==". $this->version;
		}
		return $currentversion;
	}

	function getLocalVersion()
	{
		global $langs;
		$context  = stream_context_create(array('http' => array('header' => 'Accept: application/xml')));
		$changelog = @file_get_contents(dol_buildpath($this->name, 0).'/changelog.xml', false, $context);
		$sxelast = simplexml_load_string(nl2br($changelog));
		if ($sxelast === false) 
			return $langs->trans("ChangelogXMLError");
		else {
			$tblversionslast=$sxelast->Version;
			$currentversion = (string) $tblversionslast[count($tblversionslast)-1]->attributes()->Number;
			$tblDolibarr=$sxelast->Dolibarr;
			$minversionDolibarr=$tblDolibarr->attributes()->minVersion;
			if ((int) DOL_VERSION < (int) $minversionDolibarr) {
				$this->dolibarrminversion=$minversionDolibarr;
				$this->disabled = true;
			}
		}
		return $currentversion;
	}
}