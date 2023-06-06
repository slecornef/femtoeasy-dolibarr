<?php
/* Copyright (C) 2014-2023		Charlene BENKE		<charlene@patas-monkey.com>
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
 *	\file	   htdocs/factory/class/factory.class.php
 *	\ingroup	categorie
 *	\brief	  File of class to factory
 */

/**
 *	Class to manage Factory
 */
class Factory extends CommonObject
{
	public $element='factory';
	public $picto='factory.png@factory';
	
	public $table_element='factory';
	public $fk_element='fk_factory';
	public $table_element_line='factorydet';
	
	var $id;
	var $ref;
	var $fk_product;
	var $fk_entrepot;
	var $fk_bom;

	var $description;
	var $statut;  // deprecied
	var $fk_statut;
	var $model_pdf;
	var $datec;		// date de création de l'OF

	// -----
	var $qty_planned;
	var $date_end_planned;	
	var $date_start_planned;	
	var $duration_planned;
	// -----
	var $qty_made;
	var $date_end_made;	
	var $date_start_made;	
	var $duration_made;
	
	var $is_sousproduit_qty=0;
	var $is_sousproduit_qtyglobal=0;
	var $is_sousproduit_description="";
	
	//Add for origin of create
	public $origin;
	public $origin_id;
	
	// add for subcontrating
	public $fk_soc;   			// sous traitant
	public $ref_supplier;		// ref de la commande fournisseur
	public $delivery_date; 		// date d'envoie de la commande
	public $fk_user_commande;	// user qui suis la commande
  

	const STATUS_DRAFT = 0; // état brouillon, en cours de préparation
	const STATUS_VALIDATE = 1; // état attente, le ou les mouvements ne sont pas encore réalisés
	const STATUS_CLOSE = 2; // état terminé, plus de mouvement à faire
	const STATUS_SUBCONTRACTING = 4; // OF soustraité
	
	const STATUS_CANCELED = 9; //  le mouvement n'aura pas lieu finalement

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db	 Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
		$this->fk_statut = 0;
		$this->picto = "factory@factory";

		$this->statuts_short[self::STATUS_DRAFT] = 'Draft';
		$this->statuts_short[self::STATUS_VALIDATE] = 'Validate';
		$this->statuts_short[self::STATUS_CLOSE] = 'Closed';
		$this->statuts_short[self::STATUS_SUBCONTRACTING] = 'Subcontracted';
		$this->statuts_short[self::STATUS_CANCELED] = 'Disabled';

		$this->statuts_product[0] ="NotInStock";
		$this->statuts_product[1] ="InOfStock";
		$this->statuts_product[2] ="InGlobalStock";
		$this->statuts_product[3] ="Validated";
		$this->statuts_product_img[0] ="statut0";
		$this->statuts_product_img[1] ="statut1";
		$this->statuts_product_img[2] ="statut2";
		$this->statuts_product_img[3] ="statut3";

	}

	function selectControlingStatus($selected='',$short=0, $excludedraft=0, $showempty=1, $htmlname='controling_statut') {
		global $langs;

		print '<select class="flat" name="'.$htmlname.'">';
		if ($showempty) print '<option value="-1">&nbsp;</option>';
		if ($showempty) print '<option value="0">NothingInStock</option>';
		if ($showempty) print '<option value="1">AllInLocalStock</option>';
		if ($showempty) print '<option value="2">AllInFullStock</option>';
		print '</select>';
	}

	/**
	 *    Return combo list of differents status of a factory
	 *
	 *    @param	string	$selected   	Preselected value
	 *    @param	int		$short			Use short labels
	 *    @param	int		$excludedraft	0=All status, 1=Exclude draft status
	 *    @param	int 	$showempty		1=Add empty line
	 *    @param    string  $htmlname       Name of select field
	 *    @return	void
	 */
	function selectFactoryStatus($selected='',$short=0, $excludedraft=0, $showempty=1, $htmlname='factory_statut')
	{
		global $langs;

		//$prefix='';
		//$listofstatus=array();
		//$prefix="FactoryStatus";

		// $listofstatus=array(
		//     0=>array('id'=>0, 'code'=>'FA_DRAFT'),
		//     1=>array('id'=>1, 'code'=>'FA_OPEN'),
		//     3=>array('id'=>2, 'code'=>'FA_CLOSE'),
		//     4=>array('id'=>4, 'code'=>'FA_SUBCONTRACT'),
		// 	5=>array('id'=>9, 'code'=>'FA_CANCEL')
		// );

		print '<select class="flat" name="'.$htmlname.'">';
		if ($showempty) print '<option value="-1">&nbsp;</option>';

		foreach($this->statuts_short as $key => $value) {
			if ($excludedraft) {
				if ($key == 0 ) {
					continue;
				}
			}
			if ($selected != '' && $selected == $key) {
				print '<option value="'.$key.'" selected>';
			} else {
				print '<option value="'.$key.'">';
			}
			print $langs->trans($value);
			print '</option>';

		}
		print '</select>';
	}

	// return id of the new OF
	function createof()
	{
		$this->db->begin();
		global $user, $conf; // , $langs;
		global $orig;

		dol_include_once("/factory/core/modules/factory/".$conf->global->FACTORY_ADDON.'.php');
		$obj = $conf->global->FACTORY_ADDON;
		$modfactory = new $obj($this->db);
		$refOF = $modfactory->getNextValue();
		$this->ref=$refOF ;
		$this->fk_product = ($this->fk_product ? $this->fk_product : $this->id);
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'factory ( ref, fk_product, fk_entrepot, datec, fk_factory_parent,';
		$sql.= ' description, fk_bom, date_start_planned, date_end_planned, duration_planned, qty_planned, fk_user_author';
		$sql.= ' ) VALUES ("'.$refOF.'", '.$this->fk_product;
		$sql.= ', '.$this->fk_entrepot.', now()';
		$sql.= ', '.($this->fk_factory_parent?$this->fk_factory_parent:'0');
		$sql.= ', "'.$this->db->escape($this->description).'"';
		$sql.= ', '.($this->fk_bom?$this->fk_bom:'null');
		$sql.= ', '.($this->date_start_planned?'"'.$this->db->idate($this->date_start_planned).'"':'null');
		$sql.= ', '.($this->date_end_planned?'"'.$this->db->idate($this->date_end_planned).'"':'null');
		$sql.= ', '.($this->duration_planned?$this->duration_planned:'null');
		$sql.= ', '.($this->qty_planned?$this->qty_planned:'null').', '.$user->id.' )';

		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			$this->db->rollback();
			return -1;
		} else {
			// get the last inserted value
			$factoryid=$this->db->last_insert_id(MAIN_DB_PREFIX."factory");
			 
			$tmpid = $this->id;

			if (!empty($this->context) && $this->context['createfromclone'] == 'createfromclone') {
				// on récupère la composition de l'of Cloné
				$prods_arbo =$this->getChildsOF($orig->id); 
			} else {
				// sinon c'est ceux du produit associé à l'OF
				// on mémorise les composants utilisés pour la fabrication
				//$prodsfather = $this->getFather(); //Parent Products
				if ($this->id) {
					$this->get_sousproduits_arbo();
					$prods_arbo = $this->get_arbo_each_prod();
					// Number of subproducts
				}
			}
			// something wrong in recurs, change id of object
			$this->id = $tmpid ;
			// List of subproducts
			if (is_array($prods_arbo)) 
				if (count($prods_arbo) > 0) {
					// on boucle sur les composants	pour créer les lignes de détail
					foreach ($prods_arbo as $value) {
						$this->createof_component($factoryid, $this->qty_planned, $value, 0);

					}
				}

			// les extrafields sont a associé à l'of pas au produit
			$this->id = $factoryid;
			$result=$this->insertExtraFields();
			if ($result < 0) {
				$error++;
				$this->db->rollback();
			}
			
			// on ajoute à présent le lien (si linked)
			if (!empty($this->origin))
				$ret = $this->add_object_linked($this->origin, $this->origin_id);

		}
		if (! $error ) {
			// si il y a une mise à jour de la composition de l'OF, on met à jour le tableau
			if (!empty($conf->global->MAIN_MODULE_BOMGENERATOR)) {
				// on supprime la configuration de l'OF si besoin
				dol_include_once("/bomgenerator/class/bomgenerator.class.php");
				$factorygenerator = new factorygenerator($this->db);
				$factorygenerator->deletelines($this->fk_product);
			}
			// Call trigger
			$result=$this->call_trigger('FACTORY_CREATE', $user);
			if ($result < 0) $error++;
			// End call triggers
		}
		if (! $error) {
			$this->db->commit();
			return $factoryid;
		}
	}

	function createof_component($fk_factory, $qty_build, $valuearray, $fk_mouvementstock=0 )
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'factorydet (fk_factory, fk_product, qty_unit, qty_planned, pmp, price,';
		$sql .= ' fk_mvtstockplanned, globalqty, ordercomponent, description)';
		// pour gérer les quantités
		if ($valuearray['globalqty'] == 0)
			$qty_planned=$qty_build * $valuearray['nb'];
		else
			$qty_planned=$valuearray['nb'];
		$sql .= ' VALUES ('.$fk_factory.', '.$valuearray['id'].', '.$valuearray['nb'];
		$sql .= ', '.$qty_planned.', '.($valuearray['pmp']?$valuearray['pmp']:0);
		$sql .= ', '.($valuearray['price']?$valuearray['price']:0);
		$sql .= ', '.$fk_mouvementstock.', '.$valuearray['globalqty'];
		$sql .= ', '.($valuearray['ordercomponent']?$valuearray['ordercomponent']:0);
		$sql .= ',"'.$this->db->escape($valuearray['description']).'" )';
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		} else {

			$factorydetid=$this->db->last_insert_id(MAIN_DB_PREFIX."factorydet");

			// on récupère la liste des extrafields
			$extrafields_product_factory = New Extrafields($this->db);
			$extrafields_factorydet = New Extrafields($this->db);
			$extralabels_pf = $extrafields_product_factory->fetch_name_optionals_label("product_factory");
			$extralabels_fd = $extrafields_factorydet->fetch_name_optionals_label("factorydet");

			// si il y a des extrafields de chaque coté, on regarde si certains sont identiques
			if (is_array($extralabels_pf) && is_array($extralabels_fd)) {

				// si il y a des extrafields transférable
				$sql = "SELECT ef.* FROM ".MAIN_DB_PREFIX."product_factory as pf";
				$sql.= " ,".MAIN_DB_PREFIX."product_factory_extrafields as ef";
				$sql.= " WHERE pf.fk_product_father =".$this->fk_product;
				$sql.= " AND pf.fk_product_children =".$valuearray['id'];
				$sql.= " AND pf.rowid = ef.fk_object";

				$resql=$this->db->query($sql);
				if ($resql) {
					if ($this->db->num_rows($resql)) {
						$obj = $this->db->fetch_object($resql);
						$extrafieldsElement = New Factorydet($this->db);
						$extrafieldsElement->id = $factorydetid;
						$bValueToInsert=false;
						// on alimente les valeurs de l'extrafields
						foreach ($extralabels_fd as $key => $value) {
							// si le champs est présent et alimenté on le transfert
							if ($obj->$key) {
								$bValueToInsert=true;
								$extrafieldsElement->array_options["options_".$key]=$obj->$key;
							}
						}
						if ($bValueToInsert)
							$extrafieldsElement->insertExtraFields();
					}
				}

			}
			return 1;
		}
	}

	function fetch($rowid, $ref='')
	{
		$sql = "SELECT * FROM ".MAIN_DB_PREFIX."factory as f";
		if ($ref) $sql.= " WHERE f.ref='".$this->db->escape($ref)."'";
		else $sql.= " WHERE f.rowid=".$rowid;

		dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id				= $obj->rowid;
				$this->ref				= $obj->ref;
				$this->description  	= $obj->description;
				$this->fk_bom  			= $obj->fk_bom;
				$this->qty_planned		= $obj->qty_planned;
				$this->qty_made			= $obj->qty_made;
				$this->datec			= $this->db->jdate($obj->datec);
				$this->date_start_planned = $this->db->jdate($obj->date_start_planned);
				$this->date_start_made 	= $this->db->jdate($obj->date_start_made);
				$this->date_end_planned	= $this->db->jdate($obj->date_end_planned);
				$this->date_end_made	= $this->db->jdate($obj->date_end_made);
				$this->duration_planned	= $obj->duration_planned;
				$this->duration_made	= $obj->duration_made;
				$this->fk_product		= $obj->fk_product;
				$this->fk_entrepot		= $obj->fk_entrepot;
				$this->note_public		= $obj->note_public;
				$this->note_private		= $obj->note_private;
				$this->model_pdf		= $obj->model_pdf;
				$this->statut			= $obj->fk_statut;		// deprecied
				$this->fk_statut		= $obj->fk_statut;

				// for subcontracting
				$this->thirdparty 	= null; 	// Clear if another value was already set by fetch_thirdparty
				$this->fk_soc				= $obj->fk_soc;
				$this->ref_supplier			= $obj->ref_supplier;
				$this->delivery_date		= $this->db->jdate($obj->delivery_date);
				$this->fk_user_commande		= $obj->fk_user_commande;

				// si erreur sur le statut on le corrige directement
				if ($obj->fk_statut == 3) {
					$sql = "UPDATE ".MAIN_DB_PREFIX."factory ";
					$sql.= " SET fk_statut = 9";
					$sql.= " WHERE rowid = ".$obj->rowid;
					$this->db->query($sql);
				}

				$this->extraparams	= (array) json_decode($obj->extraparams, true);

				$this->db->free($resql);

				// on récupère le produit fabriqué comme une ligne de détail pour la création de commande fourn
				if ($this->fk_soc > 0)
					$this->fetch_lines();

				return 1;
			}
		} else {
			$this->error=$this->db->error();
			dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
			return -1;
		}
	}	
	
	/**
	 * 	Information sur l'objet fiche intervention
	 *
	 *	@param	int		$id	  Id de la fiche d'intervention
	 *	@return	void
	 */
	function info($id)
	{
		global $conf;

		$sql = "SELECT f.rowid,";
		$sql.= " datec,";
		$sql.= " date_start_planned,";
		$sql.= " date_start_planned,";
		$sql.= " date_start_made,";
		$sql.= " date_end_planned,";
		$sql.= " date_end_made,";
		$sql.= " fk_user_author,";
		$sql.= " fk_user_valid,";
		$sql.= " fk_user_close";
		$sql.= " FROM ".MAIN_DB_PREFIX."factory as f";
		$sql.= " WHERE f.rowid = ".$id;

		$result = $this->db->query($sql);

		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);

				$this->id	= $obj->rowid;
				
				$this->date_creation = $this->db->jdate($obj->datec);
				$this->date_debut = $this->db->jdate($obj->date_start_planned);

				$this->date_cloture = $this->db->jdate($obj->date_end_planned);

				if ($obj->date_start_made)
					$this->date_debut = $this->db->jdate($obj->date_start_made);

				if ($obj->date_end_made)
					$this->date_cloture = $this->db->jdate($obj->date_end_made);

				if ($obj->fk_user_author) {
					$cuser = new User($this->db);
					$cuser->fetch($obj->fk_user_author);
					$this->user_creation = $cuser;
				}

				if ($obj->fk_user_valid) {
					$vuser = new User($this->db);
					$vuser->fetch($obj->fk_user_valid);
					$this->user_validation = $vuser;
				}

				if ($obj->fk_user_close) {
					$euser = new User($this->db);
					$euser->fetch($obj->fk_user_close);
					$this->user_cloture = $euser;
				}
			}
			$this->db->free($result);
		}
		else
			dol_print_error($this->db);
	}

	/**
	 *  Lie un produit associe au produit/service
	 *
	 *  @param	  int		$id_pere		Id du produit auquel sera lie le produit a lier
	 *  @param	  int		$id_fils		Id du produit a lier
	 *  @param		int		$qty			Quantity
	 *  @param		double	$pmp			buy price
	 *  @param		double	$price			sell price
	 *  @param		int		$qtyglobal		Quantity is a global value
	 *  @param		string	$description	description
	 *  @param		int		$ordercomponent	order of composition
	 *  @return	 int						< 0 if KO, > 0 if OK
	 */
	function add_component($fk_parent, $fk_child, $qty, $pmp=0, $price=0, $qtyglobal=0, $description='', $ordercomponent=0)
	{

		$sql = 'DELETE from '.MAIN_DB_PREFIX.'product_factory';
		$sql .= ' WHERE fk_product_father  = '.$fk_parent.' AND fk_product_children = '.$fk_child;
		//print $sql.'<br>';
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		} else {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'product_factory(fk_product_father, fk_product_children,';
			$sql .= 'qty, pmp, price, globalqty, description, ordercomponent)';
			$sql .= ' VALUES ('.$fk_parent.', '.$fk_child.', '.price2num($qty).', '.price2num($pmp).', '.price2num($price);
			$sql .= ', '.($qtyglobal?$qtyglobal:'0').', "'.$this->db->escape($description).'"';
			$sql .= ', '.($ordercomponent?$ordercomponent:'0');
			$sql .= ' )';
			//print $sql.'<br>';
			if (! $this->db->query($sql)) {
				dol_print_error($this->db);
				return -1;
			} else {
				// si l'insert est ok, on alimente les extrafields si il en a
				$newid =$this->db->last_insert_id(MAIN_DB_PREFIX."product_factory");
				return $newid;
			}
		}
	}

	/**
	 *  Lie un produit associe au produit/service
	 *
	 *  @param	  int		$id_pere		Id du produit auquel sera lie le produit a lier
	 *  @param	  int		$id_fils		Id du produit a lier
	 *  @param		int		$qty			Quantity
	 *  @param		double	$pmp			buy price
	 *  @param		double	$price			sell price
	 *  @param		int		$qtyglobal		Quantity is a global value
	 *  @param		string	$description	description
	 *  @param		int		$ordercomponent	order of composition
	 *  @return	 int						< 0 if KO, > 0 if OK
	 */
	function add_componentOF($fk_factory, $fk_product, $qty, $pmp=0, $price=0, $qtyglobal=0, $description='', $ordercomponent=0)
	{
		// on supprime la précédente saisie (clé d'intégrité )
		$sql = 'DELETE from '.MAIN_DB_PREFIX.'factorydet';
		$sql .= ' WHERE fk_factory = '.$fk_factory.' AND fk_product= '.$fk_product;
		$sql .= ' AND globalqty='.((int) $qtyglobal);
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		} else {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'factorydet(fk_factory, fk_product,';
			$sql .= 'qty_unit, qty_planned, pmp, price, globalqty, description, ordercomponent)';
			$sql .= ' VALUES ('.$fk_factory.', '.$fk_product.', '.price2num($qty);
			if ($qtyglobal == 1)
				$sql .= ', '.price2num($qty);
			else
				$sql .= ', '.price2num($qty * $this->qty_planned);
			$sql .= ', '.price2num($pmp).', '.price2num($price);
			$sql .= ', '.($qtyglobal?$qtyglobal:'0').', "'.$this->db->escape($description).'"';
			$sql .= ', '.($ordercomponent?$ordercomponent:'0');
			$sql .= ' )';
			if (! $this->db->query($sql)) {
				dol_print_error($this->db);
				return -1;
			} else {
				// si l'insert est ok, on alimente les extrafields si il en a
				$newid =$this->db->last_insert_id(MAIN_DB_PREFIX."factorydet");
				return $newid;
			}
		}
	}

		/**
	 *  remplace un produit par un autre au niveau de la composition
	 *
	 *  @param		int		$fk_product	Id du produit qui va remplacer
	 *  @param		array	$productList	tableau des produits à changer
	 *  @return		int		< 0 if KO, > 0 if OK
	 */
	function productChange($fk_product, $productList) {
		foreach ($productList as $productchange) {
			if (is_sousproduit($productchange, $fk_product)) {
				// on récupère les infos du produit cible
				$destQty = $this->is_sousproduit_qty;
				$destQtyGlobal =$this->is_sousproduit_qtyglobal;
				// on récupère les infos du produit à transférer
				$ret = is_sousproduit($productchange, $this->id);
				// cas simple, on a le meme mode de fonctionnement
				if ($destQtyGlobal == $this->is_sousproduit_qtyglobal) {
					// on supprime la ligne
					$sql = 'DELETE '.MAIN_DB_PREFIX.'product_factory';
					$sql .= ' WHERE fk_product_children = '.$this->id;
					$sql .= ' AND fk_product_father = '.$productchange;
					$this->db->query($sql);

					$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_factory';
					$sql .= ' SET qty = '.$destQty + $this->is_sousproduit_qty;
					$sql .= ' WHERE fk_product_children = '.$fk_product;
					$sql .= ' AND fk_product_father = '.$productchange;
					$this->db->query($sql);
				} else {
					// bon la plus galère on va éviter pour le moment...
				}


			}
			else {
				// remplacement simple
				$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_factory';
				$sql .= ' SET fk_product_children = '.$fk_product;
				$sql .= ' WHERE fk_product_children = '.$this->id;
				$sql .= ' AND fk_product_father = '.$productchange;
				$this->db->query($sql);
			}
		}
	}


	/**
	 *  Lie un produit associe à une tache
	 *
	 *  @param	  int	$id_pere	Id du produit auquel sera lie le produit a lier
	 *  @param	  int	$id_fils	Id du produit a lier
	 *  @param		int	$qty		Quantity
	 *  @param		double	$pmp	buy price
	 *  @param		double	$price	sell price
	 *  @return	 int				< 0 if KO, > 0 if OK
	 */
	function add_componenttask($fk_task, $fk_product, $qty_planned, $pmp=0, $price=0)
	{
		if ($pmp =='')
			$pmp =0;
		if ($price =='')
			$price =0;
			
		// dans le doute on supprime toujours la ligne
		$sql = 'DELETE from '.MAIN_DB_PREFIX.'projet_taskdet';
		$sql .= ' WHERE fk_task = '.$fk_task.' AND fk_product = '.$fk_product;
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		} else {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'projet_taskdet(fk_task, fk_product, qty_planned, pmp, price)';
			$sql .= ' VALUES ('.$fk_task.', '.$fk_product.', ';
			$sql .= price2num($qty_planned).', '.price2num($pmp).', '.price2num($price).' )';
			if (! $this->db->query($sql)) {
				dol_print_error($this->db);
				return -1;
			} else
				return 1;
		}
	}

	/**
	 *  Verifie si c'est un sous-produit
	 *
	 *  @param	  int	$fk_parent		Id du produit auquel le produit est lie
	 *  @param	  int	$fk_child		Id du produit lie
	 *  @param	  int	$basetable		Id du produit lie
	 
	 *  @return	 int					< 0 si erreur, > 0 si ok
	 */
	function is_sousproduit($fk_parent, $fk_child)
	{
		$sql = "SELECT qty, globalqty, description, ordercomponent";
		$sql.= " FROM ".MAIN_DB_PREFIX."product_factory";
		$sql.= " WHERE fk_product_father  = ".$fk_parent;
		$sql.= " AND fk_product_children = ".$fk_child;

		$result = $this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);

			if ($num > 0) {
				$obj = $this->db->fetch_object($result);
				$this->is_sousproduit_qty = $obj->qty;
				$this->is_sousproduit_qtyglobal = $obj->globalqty;
				$this->is_sousproduit_description = $obj->description;
				$this->is_sousproduit_ordercomponent = $obj->ordercomponent;
				return true;
			} else
				return false;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

	function get_lineid($fk_parent, $fk_child)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'product_factory';
		$sql .= ' WHERE fk_product_children = '.$fk_child;
		$sql .= ' AND fk_product_father = '.$fk_parent;

		$result = $this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);
			if ($num > 0) {
				$obj = $this->db->fetch_object($result);

				return $obj->rowid;
			}
		}
		return 0;
	}

	/**
	 *  Verifie si c'est un sous-produit
	 *
	 *  @param	  int	$fk_parent		Id du produit auquel le produit est lie
	 *  @param	  int	$fk_child		Id du produit lie
	 *  @param	  int	$basetable		Id du produit lie
	 
	 *  @return	 int					< 0 si erreur, > 0 si ok
	 */
	function is_sousproduitOF($fk_factory, $fk_child)
	{
		$sql = "SELECT qty_unit, globalqty, description, ordercomponent";
		$sql.= " FROM ".MAIN_DB_PREFIX."factorydet";
		$sql.= " WHERE fk_factory = ".$fk_factory;
		$sql.= " AND fk_product = ".$fk_child;

		$result = $this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);

			if ($num > 0) {
				$obj = $this->db->fetch_object($result);
				$this->is_sousproduit_qty = $obj->qty_unit;
				$this->is_sousproduit_qtyglobal = $obj->globalqty;
				$this->is_sousproduit_description = $obj->description;
				$this->is_sousproduit_ordercomponent = $obj->ordercomponent;
				return true;
			} else
				return false;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

	// check si sous-traitance 
	function CheckProductStockOk($qty_product_ok) {
		global $langs;

		$ret= img_picto(
				$langs->trans($this->statuts_product[$qty_product_ok]), 
				$this->statuts_product_img[$qty_product_ok]
			);
		// si pas en stock on autorise une validation manuelle
		if ($qty_product_ok == 0) 
			$ret ="<a href>".$ret."</a>";
		return $ret; 
	}

	/**
	 *  Initialise an instance with random values.
	 *  Used to build previews or test instances.
	 *	id must be 0 if object instance is a specimen.
	 *
	 *  @return	void
	 */
	function initAsSpecimen()
	{
		global $user, $langs, $conf;

		$now=dol_now();

		// Initialise parametres
		$this->id=0;
		$this->ref = 'SPECIMEN';
		$this->specimen=1;
		$this->socid = 1;
		$this->date = $now;
		$this->note_public='SPECIMEN';
		$this->duree = 0;
		$nbp = 5;
		$xnbp = 0;
		while ($xnbp < $nbp) {
			$line = New Factorydet($this->db);
			$this->lines[$xnbp]=$line;
			$xnbp++;
		}
	}


	/**
	 *  Retire le lien entre un sousproduit et un produit/service
	 *
	 *  @param	  int	$fk_parent		Id du produit auquel ne sera plus lie le produit lie
	 *  @param	  int	$fk_child		Id du produit a ne plus lier, 0 si tous les d�lier
	 *  @return	 int					< 0 si erreur, > 0 si ok
	 */
	function del_component($fk_parent, $fk_child=0)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."product_factory";
		$sql.= " WHERE fk_product_father  = '".$fk_parent."'";
		if ($fk_child > 0)
			$sql.= " AND fk_product_children = '".$fk_child."'";

		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		}

		return 1;
	}
	
	/**
	 *  Retire le lien entre un sousproduit et un produit/service dans l'of
	 *
	 *  @param	  int	$fk_factory		la ligne de l'of
	 *  @param	  int	$fk_child		la ligne du produit (si 0 tous les produits d'un coups)
	 *  @return	 int					< 0 si erreur, > 0 si ok
	 */
	function del_componentOF($fk_factory, $fk_product=0)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."factorydet";
		$sql.= " WHERE fk_factory = ".$fk_factory;
		if ($fk_product > 0)
			$sql.= " AND fk_product = ".$fk_product;

		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		}

		return 1;
	}

	/**
	 *  Retire le lien entre un sousproduit et un produit/service dans l'of
	 *
	 *  @param	  int	$fk_factory		la ligne de l'of
	 *  @param	  int	$fk_child		la ligne du produit (si 0 tous les produits d'un coups)
	 *  @return	 int					< 0 si erreur, > 0 si ok
	 */
	function deleteOF($fk_factory)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."factory";
		$sql.= " WHERE rowid = ".$fk_factory;
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		}
		return 1;
	}

	/**
	 *  Retire le lien entre un sousproduit et un produit/service
	 *
	 *  @param	  int	$fk_parent		Id du produit auquel ne sera plus lie le produit lie
	 *  @param	  int	$fk_child		Id du produit a ne plus lie
	 *  @return	 int					< 0 si erreur, > 0 si ok
	 */
	function del_componenttask($fk_task, $fk_product)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."projet_taskdet";
		$sql.= " WHERE fk_task  = ".$fk_task;
		$sql.= " AND fk_product = ".$fk_product;

		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		}

		return 1;
	}

	// récupération du nombre de produit en cours de fabrication
	function getQtylink ($orderid, $fk_product)
	{
		$sql = "SELECT sum(qty_planned) as total_planned, sum(qty_made) as total_made";
		$sql.= " FROM ".MAIN_DB_PREFIX."element_element as ee , ".MAIN_DB_PREFIX."factory as f";
		$sql.= " WHERE ee.fk_target = f.rowid";
		$sql.= " AND sourcetype='commande' AND targettype='factory'";
		$sql.= " AND ee.fk_source = ".$orderid;
		$sql.= " AND f.fk_product = ".$fk_product;

		$result = $this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);
			if ($num > 0) {
				$obj = $this->db->fetch_object($result);
				if ($obj->total_made > 0)
					return $obj->total_made;
				else
					return $obj->total_planned;
			} else
				return 0;
		}
	}

		/**
	 *	Return number of product buildable in entrepot 
	  *
	 * 	@param	int		$fk_product		id of the product
	 * 	@param	int		$fk_status		status of OF (-1 = All)
	 *  @return	int						number of OF of product
	 */
	function getNbOfByStatus($fk_product, $fk_statut =-1) {
		$sql = "SELECT count(*) as nb ";
		$sql.= " FROM ".MAIN_DB_PREFIX."factory as f";
		$sql.= " WHERE f.fk_product = ".$fk_product;

		if ($fk_statut == 1)
			$sql.= " AND f.fk_statut < 2";
		elseif ($fk_statut == 2)
			$sql.= " AND f.fk_statut > 1";

		$res = $this->db->query($sql);
		if ($res) {
			$objp = $this->db->fetch_object($res);
			return $objp->nb;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

	/**
	 *	Return number of product buildable in entrepot 
	  *
	 * 	@param	int		$entrepotid		id of the entrepot
	 * 	@param	int		$productid		id of the product to build
	 *  @return	int						number of product buildable
	 */
	function getNbProductBuildable($entrepotid, $productid)
	{
		$this->id=$productid;
		//$this->fetch($productid);
		
		$fabricable=0;
		$this->get_sousproduits_arbo();
		$prods_arbo = $this->get_arbo_each_prod();
		if (count($prods_arbo) > 0) {
			$fabricable=-1;
			$bAllService = true;
			foreach ($prods_arbo as $value) {
				$productstatic = new Product($this->db);
				$productstatic->id=$value['id'];
				$productstatic->fetch($value['id']);

				if (empty($value['type']) || $value['type'] ==0) {
					// we have at list a service
					$bAllService = false;
					$productstatic->load_stock();
					// for the first loop, buildable is the stock divide by number need
					if ($fabricable==-1)
						$fabricable=$productstatic->stock_warehouse[$entrepotid]->real/$value['nb'];
					else {
						// other loop, buildable changed only if the number is smaller
						if ($fabricable >= $productstatic->stock_reel/$value['nb'])
							$fabricable=$productstatic->stock_warehouse[$entrepotid]->real/$value['nb'];
					}
				}
				// print 'fabricable='.$fabricable.' - stock='.$productstatic->stock_warehouse[$entrepotid]->real.' - nb='.$value['nb'].'<br>';
			}
		}
		if ($bAllService)
			return -2;
		// attention buildable product are always an integer
		return (int) $fabricable;
	}
	
	function get_nb_ProductInTask($taskid, $productid)
	{
		$sql = "SELECT qty_planned as qtyplanned";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_taskdet as ptd";
		$sql.= " WHERE ptd.fk_task = ".$taskid;
		$sql.= " AND ptd.fk_product=".$productid;

		$res = $this->db->query($sql);
		if ($res) {
			//$objp = $this->db->fetch_array($res);
			$objp = $this->db->fetch_object($res);
			return $objp->qtyplanned;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

	function getQtyFromStock($projectid, $productid)
	{

		$sql = "SELECT sum(qty_from_stock) as nbinproject ";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_stock as ps";
		$sql.= " WHERE ps.fk_project = ".$projectid;
		$sql.= " AND ps.fk_product= ".$productid;
		$res = $this->db->query($sql);

		if ($res) {
			$obj = $this->db->fetch_object($res);
			return $obj->nbinproject;
		}
		return 0;
	}
	
	function get_value_ProductInTask($taskid, $productid, $valuetype, $defaultvalue=0)
	{
		$sql = "SELECT pmp, price FROM ".MAIN_DB_PREFIX."projet_taskdet as ptd";
		$sql.= " WHERE ptd.fk_task = ".$taskid;
		$sql.= " AND ptd.fk_product=".$productid;
		$res = $this->db->query($sql);
		if ($res) {
			//$objp = $this->db->fetch_array($res);
			$objp = $this->db->fetch_object($res);
			if ($valuetype=='pmp')
				return ($objp->pmp ? $objp->pmp : $defaultvalue);
			else
				return ($objp->price ? $objp->price : $defaultvalue);
		}
		return 0;
	}
	
	/**
	 *  Fonction recursive uniquement utilisee par get_arbo_each_prod, recompose l'arborescence des sousproduits
	 * 	Define value of this->res
	 *
	 *	@param		array	$prod			Products array
	 *	@param		string	$compl_path		Directory path
	 *	@param		int		$multiply		Because each sublevel must be multiplicated by parent nb
	 *	@param		int		$level			Init level
	 *  @return 	void
	 */
	function fetch_prod_arbo($prod, $compl_path="", $multiply=1, $level=1)
	{
		global $conf, $langs;
		foreach ($prod as $nom_pere => $desc_pere) {
			$product = new Product($this->db);
			if (is_array($desc_pere)) {
				// If this parent desc is an array, this is an array of childs
				//var_dump($desc_pere);
				$id=(! empty($desc_pere[0]) ? $desc_pere[0] :'');
				$nb=(! empty($desc_pere[1]) ? $desc_pere[1] :'0');
				$type=(! empty($desc_pere[2]) ? $desc_pere[2] :'');
				$label=(! empty($desc_pere[3]) ? $desc_pere[3] :'');
				$pmp=(! empty($desc_pere[4]) ? $desc_pere[4] :'0');
				$price=(! empty($desc_pere[5]) ? $desc_pere[5] :'0');
				$globalqty=(! empty($desc_pere[6]) ? $desc_pere[6] :'0');
				$description=(! empty($desc_pere[7]) ? $desc_pere[7] :'');
				$ordercomponent=(! empty($desc_pere[8]) ? $desc_pere[8] :'');


				//print "XXX ".$desc_pere[1]." nb=".$nb." multiply=".$multiply."<br>";
				//$img="";
				$product->fetch($id);
				$product->load_stock();
				$stockReal=0;
				if (!empty($product->stock_warehouse))
					$stockReal= $product->stock_warehouse[1]->real;

				//if ( $stockReal < $product->seuil_stock_alerte)
				//	$img=img_warning($langs->trans("StockTooLow"));

				// si en quantité global on ne gère pas de la même façon les quantités
				if ($globalqty == 0)
					$nb_total = $nb*$multiply;
				else
					$nb_total = $nb;

				$this->res[]= array(
					'id'=>$id,									// Id product
					'label'=>$label,							// label product
					'pmp'=>$pmp,								// pmp of the product
					'price'=>$price,							// price of the product
					'nb'=>$nb,									// Nb of units that compose parent product
					'nb_total'=>$nb_total,					// Nb of units for all nb of product
					'stock'=>$stockReal,						// Stock
					'stock_alert'=>$product->seuil_stock_alerte,	// Stock alert
					'fullpath' => $compl_path.$nom_pere,		// Label
					'type'=>$type,								// Nb of units that compose parent product
					'globalqty'=>$globalqty,					// Nb of units that compose parent product
					'description'=>$description,				// description additionnel sur l'of
					'ordercomponent'=>$ordercomponent			// ordre de la composition
				);
			} elseif ($nom_pere != "0" && $nom_pere != "1")
				$this->product[]= array($compl_path.$nom_pere, $desc_pere);
		}
	}

	/**
	 *  fonction recursive uniquement utilisee par get_each_prod, ajoute chaque sousproduits dans le tableau res
	 *
	 *	@param	array	$prod	Products array
	 *  @return void
	 */
	function fetch_prods($prod)
	{
		$this->res = array();
		//var_dump($prod);
		foreach ($prod as $nom_pere => $desc_pere) {
			// on est dans une sous-categorie
			if (is_array($desc_pere))
				$this->res[]= array($desc_pere[1], $desc_pere[0]);
	
			if (count($desc_pere) >1)
				$this->fetch_prods($desc_pere);
		}
	}

	/**
	 *  reconstruit l'arborescence des composants sous la forme d'un tableau
	 *
	 *	@param		int		$multiply		Because each sublevel must be multiplicated by parent nb
	 *  @return 	array 					$this->res
	 */
	function get_arbo_each_prod($multiply=1)
	{
		$this->res = array();
		//var_dump($this->sousprods);
		if (isset($this->sousprods) && is_array($this->sousprods)) {
			foreach ($this->sousprods as $nom_pere => $desc_pere)
				if (is_array($desc_pere)) 
					$this->fetch_prod_arbo($desc_pere, "", $multiply);
		}
		return $this->res;
	}

	/**
	 *  Renvoie tous les sousproduits dans le tableau res, chaque ligne de res contient : id -> qty
	 *
	 *  @return array $this->res
	 */
	function get_each_prod()
	{
		$this->res = array();
		if (is_array($this->sousprods)) {
			foreach ($this->sousprods as $nom_pere => $desc_pere)
				if (count($desc_pere) >1)
					$this->fetch_prods($desc_pere);
			sort($this->res);
		}
		return $this->res;
	}


	/**
	 *  Return all Father products fo current product
	 *
	 *  @return 	array prod
	 */
	function getFather($factoryid=0)
	{
		// si on veut les parents d'un of
		if ($factoryid > 0) {
			// prend dans la detail de l'of : factorydet
			$sql = "SELECT p.label as label, p.rowid, p.fk_product_type";
			//$sql.= ", pf.fk_factory as id";
			$sql.= " FROM ".MAIN_DB_PREFIX."factorydet as pf,";
			$sql.= " ".MAIN_DB_PREFIX."product as p";
			$sql.= " WHERE p.rowid = pf.fk_product";
			$sql.= " AND pf.fk_factory=".$factoryid;
			
		} else {
			// si on est sur le bom
			if ($this->fk_bom > 0) {
				// prend dans la composition
				$sql = "SELECT p.label as label, p.rowid, p.fk_product_type, bl.qty, bl.qty_frozen as globalqty";
				//$sql.= " , pf.fk_product_father as id";
				$sql.= " FROM ".MAIN_DB_PREFIX."bom_bomline as bl,";
				$sql.= " ".MAIN_DB_PREFIX."product as p";
				$sql.= " WHERE p.rowid = bl.fk_product";
				$sql.= " AND bl.fk_bom=".$this->fk_bom;
			} else {
				// prend dans la composition
				$sql = "SELECT p.label as label, p.rowid, p.fk_product_type, pf.qty, pf.globalqty";
				//$sql.= " , pf.fk_product_father as id";
				$sql.= " FROM ".MAIN_DB_PREFIX."product_factory as pf,";
				$sql.= " ".MAIN_DB_PREFIX."product as p";
				$sql.= " WHERE p.rowid = pf.fk_product_father";
				$sql.= " AND pf.fk_product_children=".$this->id;
			}
		}

		$res = $this->db->query($sql);
		if ($res) {
			$prods = array ();
			while ($record = $this->db->fetch_array($res)) {
				$prods[$record['rowid']]['id'] =  $record['rowid'];
				$prods[$record['rowid']]['label'] =  $this->db->escape($record['label']);
				$prods[$record['rowid']]['fk_product_type'] =  $record['fk_product_type'];
				$prods[$record['rowid']]['qty'] =  $record['qty'];
				$prods[$record['rowid']]['globalqty'] =  $record['globalqty'];
			}
			return $prods;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}


	/**
	 *  Return all parent products fo current product
	 *
	 *  @return 	array prod
	 */
	function getParent()
	{
		// si on se base sur la structure par d�faut de l'of
		$sql = "SELECT p.label as label, p.rowid as id";
		$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
		$sql.= " WHERE p.rowid = ".$this->id;

		$res = $this->db->query($sql);
		if ($res) {
			$prods = array ();
			while ($record = $this->db->fetch_array($res))
				$prods[$this->db->escape($record['label'])] = array(0=>$record['id']);
			return $prods;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

	/**
	 *  Return all parent products fo current product
	 *
	 *  @return 	array prod
	 */
	function getComponentOF($factoryid)
	{
		$sql = "SELECT p.rowid,  p.label as label, fd.qty_planned as qty, fd.pmp as pmp, fd.price as price";
		$sql.= " FROM ".MAIN_DB_PREFIX."factorydet as fd,";
		$sql.= " ".MAIN_DB_PREFIX."product as p";
		$sql.= " WHERE fd.fk_product = p.rowid";
		$sql.= " AND fd.fk_factory = ".$factoryid;
		$sql.= " ORDER BY fd.ordercomponent";

		$res = $this->db->query($sql);
		if ($res) {
			$prods = array ();
			while ($record = $this->db->fetch_array($res))
				$prods[$this->db->escape($record['label'])] = array(0=>$record['id']);
			return $prods;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

	// propre à la soustraitance et la création de la commande fournisseur
	function fetch_lines() 
	{
		global $conf;
		$selpricemode = $conf->global->FACTORY_COMPONENT_BUYINGPRICE;
		
		$sql = "SELECT f.rowid , f.rowid as id, p.rowid as fk_product, p.label as label, f.qty_planned as qty";
		// on a bien besoin des deux product_type
		$sql.= " , p.ref, p.fk_product_type, p.fk_product_type as product_type , p.tva_tx";
		$sql.= " , f.rowid as fk_factory, p.pmp, p.price";
		// on prend le bon prix d'achat selon le paramétrage
		if ($selpricemode == 'costprice')
			$sql.= ", p.costprice  as subprice";
		else
			$sql.= ", p.pmp as subprice";

		$sql.= " FROM ".MAIN_DB_PREFIX."factory as f";
		$sql.= " INNER JOIN ".MAIN_DB_PREFIX."product as p ON f.fk_product = p.rowid";
		$sql.= " WHERE f.rowid = ".$this->id;

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$newline = new Factorydet($this->db);
				$newline->setVarsFromFetchObj($obj);
				if ($selpricemode == 'fournishmore' || $selpricemode == 'fournishless') {
					if ($selpricemode == 'fournishmore')
						$sql = "SELECT quantity, MAX(price) AS pricefourn";
					else	// récup du prix fournisseur le plus haut
						$sql = "SELECT quantity, MIN(price) AS pricefourn";
					$sql.= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price ";
					$sql.= " WHERE fk_product=".$obj->fk_product;
					// on ne prend que les prix par quantité compatible avec la fabrication
					$sql.= " AND quantity <= ".$obj->qty;
					$sql.= " GROUP BY quantity";
					$sql.= " ORDER BY quantity";
					$resql = $this->db->query($sql);
					if ($resql) {
						$objsuppPrice = $this->db->fetch_object($resql);
						$newline->subprice = $objsuppPrice->pricefourn;
					}
				}
				$this->lines[] = $newline;
				// on met à jour les infos de prix de l'OF pour le transfert
				$this->total_ht = $newline->subprice * $obj->qty;
				$this->total_tva = $this->total_ht * ($obj->tva_tx/100);
				$this->total_ttc =$this->total_ht + $this->total_tva;
			}
			return 1;
		}
	}

	function getExportComposition($tblCompositionLine)
	{
		$tmp.="<?xml version='1.0' encoding='ISO-8859-1'?>\n";
		$tmp.="<FactoryComposition>\n";
		// récupération des champs associés au customtabs
		$tmp.="<FactoryCompositionLines>\n";
		foreach ($tblCompositionLine as $key => $value) {
			$tmp.="\t".'<FactoryCompositionLine>'."\n";
			$tmp.="\t \t<productid>".$value['id']."</productid>\n";
			$tmp.="\t \t<nb>".$value['nb']."</nb>\n";
			$tmp.="\t \t<pmp>".$value['pmp']."</pmp>\n";
			$tmp.="\t \t<price>".$value['price']."</price>\n";
			$tmp.="\t \t<globalqty>".$value['globalqty']."</globalqty>\n";
			$tmp.="\t \t<description>".$value['description']."</description>\n";
			$tmp.="\t \t<ordercomponent>".$value['ordercomponent']."</ordercomponent>\n";
			$tmp.="\t".'</FactoryCompositionLine>'."\n";
		}
		$tmp.="</FactoryCompositionLines>\n";
		$tmp.="</FactoryComposition>\n";
		return $tmp;
	}

	function importComposition($xml)
	{
		// on récupère le fichier et on le parse
		libxml_use_internal_errors(true);
		$sxe = simplexml_load_string($xml);
		if ($sxe === false) {
			echo "Erreur lors du chargement du XML\n";
			foreach (libxml_get_errors() as $error) {
				echo "\t", $error->message;
			}
		}
		else
			$arraydata = json_decode(json_encode($sxe), TRUE);
		
		// on vire la précédente composition
		$this->del_component($this->id);
		$tblfields=$arraydata['FactoryCompositionLines'];
		$tblfields=$tblfields['FactoryCompositionLine'];
		// si il y a des données à importer
		if ($tblfields) {
			foreach ($tblfields as $fields) {
				$this->add_component($this->id, $fields['productid'],
								$fields['nb'], 
								$fields['pmp'], 
								$fields['price'], 
								$fields['globalqty'], 
								(String) $fields['description'],
								$fields['ordercomponent']
				);
			}
		}

	}

	function getexportOF($id)
	{
		$this->fetch($id);
		$tmp.="<?xml version='1.0' encoding='ISO-8859-1'?><FactoryOF>\n";
		$tmp.="<ref>".$this->ref."</ref>\n";
		$tmp.="<fk_product>".$this->fk_product."</fk_product>\n";
		$tmp.="<description>".$this->description."</description>\n";
		$tmp.="<qty_planned>".$this->qty_planned."</qty_planned>\n";
		$tmp.="<fk_entrepot>".$this->fk_entrepot."</fk_entrepot>\n";
		$tmp.="<duration_planned>".$this->duration_planned."</duration_planned>\n";
		
		// récupération de la composition de l'of
		$tmp.="<FactoryOFLines>\n";
		$tblOFLine = $this->getChildsOF($id);

		foreach ($tblOFLine as $key => $value) {
			$tmp.="\t".'<FactoryOFLine>'."\n";
			$tmp.="\t \t<productid>".$key."</productid>\n";
			$tmp.="\t \t<ref>".$value['ref']."</ref>\n";
			$tmp.="\t \t<label>".$value['label']."</label>\n";
			$tmp.="\t \t<price>".$value['price']."</price>\n";
			$tmp.="\t \t<qtyunit>".$value['nb']."</qtyunit>\n";
			$tmp.="\t \t<globalqty>".$value['globalqty']."</globalqty>\n";
			$tmp.="\t \t<description>".$value['description']."</description>\n";
			$tmp.="\t \t<qtyused>".$value['qtyused']."</qtyused>\n";
			$tmp.="\t \t<qtydeleted>".$value['qtydeleted']."</qtydeleted>\n";
			$tmp.="\t \t<qtyplanned>".$value['qtyplanned']."</qtyplanned>\n";
			$tmp.="\t \t<mvtstockplanned>".$value['mvtstockplanned']."</mvtstockplanned>\n";
			$tmp.="\t \t<mvtstockused>".$value['mvtstockused']."</mvtstockused>\n";
			$tmp.="\t \t<fk_product_type>".$value['type']."</fk_product_type>\n";
			$tmp.="\t \t<ordercomponent>".$value['ordercomponent']."</ordercomponent>\n";
			$tmp.="\t".'</FactoryOFLine>'."\n";
		}
		$tmp.="</FactoryOFLine>\n";
		$tmp.="</FactoryOF>\n";
		return $tmp;
	}


	function importCompositionOF($xml)
	{
		// on récupère le fichier et on le parse
		libxml_use_internal_errors(true);
		$sxe = simplexml_load_string($xml);
		if ($sxe === false) {
			echo "Erreur lors du chargement du XML\n";
			foreach (libxml_get_errors() as $error) {
				echo "\t", $error->message;
			}
		} else
			$arraydata = json_decode(json_encode($sxe), TRUE);

		// on vire la précédente composition
		$this->del_componentOF($this->id);
		$tblfields=$arraydata['FactoryOFLines']['FactoryOFLine'];
		
		foreach ($tblfields as $fields) {
			$newArray=array();
			$newArray['fk_product'] = $fields['productid'];
			$newArray['ref'] = $fields['ref'];
			$newArray['label'] = $fields['label'];
			$newArray['price'] = $fields['price'];
			$newArray['qtyunit'] = $fields['qtyunit'];
			$newArray['description'] = $fields['description'];
			$newArray['qtyplanned'] = $fields['qtyplanned'];
			$newArray['fk_product_type'] = $fields['fk_product_type'];
			$newArray['ordercomponent'] = $fields['ordercomponent'];
			$this->createof_component($this->rowid, $this->qty_planned, $newArray);
		}

	}

	/**
	 *	Returns the label status
	 *
	 *	@param	  int		$mode	   
	 *	@param	  int		$noentities 0=use classic translation, 1=use noentities translation (for pdf print)
	 *	@return	 string	  		Label
	 */
	function getLibStatut($mode=0, $noentities=0)
	{
		return $this->LibStatut($this->fk_statut, $mode, $noentities);
	}
	

	/**
	 *	Returns the label of a statut
	 *
	 *	@param	  int		$statut	 id statut
	 *	@param	  int		$mode	   
	 *	@return	 string	  		Label
	 */
	 

	function LibStatut($status, $mode = 0, $noentities=0)
	{
		global $langs;
		if ($noentities == 1) 
			return $langs->transnoentities($this->statuts_short[$status]);
		
		// pour assurer la compatibilité avec les anciennes versions de dolibarr
		if (function_exists('dolGetStatus')) {

			$this->labelStatus[$status] = $langs->trans($this->statuts_short[$status]);
			$this->labelStatusShort[$status] = $langs->trans($this->statuts_short[$status]);
		
			$statusType = 'status'.$status;
			//if ($status == self::STATUS_VALIDATED) $statusType = 'status1';
			if ($status == 0) $statusType = 'status6';
	
			return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
		} else
			return $this->LibStatutOld($status, $mode, $noentities);
	}


	function LibStatutOld($statut, $mode=0, $noentities=0)
	{
		global $langs;

		if ($mode == 0) {
			if ($noentities == 0)
				return $langs->trans($this->statuts[$statut]);
			else
				return $langs->transnoentities($this->statuts[$statut]);
		}
		if ($mode == 1)
			return $langs->trans($this->statuts_short[$statut]);

		if ($mode == 2)
			return img_picto(
							$langs->trans($this->statuts_short[$statut]), 
							$this->statuts_img[$statut]
			).' '.$langs->trans($this->statuts_short[$statut]);

		if ($mode == 3)
			return img_picto($langs->trans($this->statuts_short[$statut]), $this->statuts_img[$statut]);

		if ($mode == 4)
			return img_picto(
							$langs->trans($this->statuts_short[$statut]), 
							$this->statuts_img[$statut]
			).' '.$langs->trans($this->statuts[$statut]);

		if ($mode == 5)
			return $langs->trans($this->statuts_short[$statut]).' '.img_picto(
							$langs->trans($this->statuts_short[$statut]), $this->statuts_img[$statut]
			);

	}

	/**
	 *	Return clicable name (with picto eventually)
	 *
	 *	@param		int			$withpicto		0=_No picto, 1=Includes the picto in the linkn, 2=Picto only
	 *	@return		string						String with URL
	 */
	function getNomUrl($withpicto=0)
	{
		global $langs, $hookmanager;

		$result='';

		$lien = '<a href="'.dol_buildpath('/factory/fiche.php?id='.$this->id, 1).'"';
		
		$lienfin='</a>';

		$picto='factory@factory';

		$label=$langs->trans("Show").': '.$this->ref;

		$linkclose = ' title="'.dol_escape_htmltag($label, 1).'"';
		$linkclose.=' class="classfortooltip" >';
		if (! is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager=new HookManager($this->db);
		}
		$hookmanager->initHooks(array('factorydao'));
		$parameters=array('id'=>$this->id);
		// Note that $action and $object may have been modified by some hooks
		$reshook=$hookmanager->executeHooks('getnomurltooltip', $parameters, $this, $action);
		$linkclose = ($hookmanager->resPrint ? $hookmanager->resPrint : $linkclose);


		if ($withpicto) $result.=($lien.$linkclose.img_object($label, $picto).$lienfin);
		if ($withpicto && $withpicto != 2) $result.=' ';
		if ($withpicto != 2) $result.=$lien.$linkclose.$this->ref.$lienfin;
		return $result;
	}

	/**
	 *	Return clicable link of object (with eventually picto)
	 *
	 *	@param		int		$withpicto		Add picto into link
	 *	@param		string	$option			Where point the link
	 *	@param		int		$maxlength		Maxlength of ref
	 *	@return		string					String with URL
	 */
	function getNomUrlFactory($id, $withpicto=0, $option='', $maxlength=0, $productref='')
	{
		global $langs;
		global $conf;

		$result='';

		if ($option == 'index') {
			$lien = '<a href="'.dol_buildpath('/factory/product/', 1).'index.php?id='.$id.'">';
			$lienfin='</a>';
		} elseif ($option == 'fiche') {
			$lien = '<a href="'.dol_buildpath('/factory/product/', 1).'fiche.php?id='.$id.'">';
			$lienfin='</a>';
		} elseif ($option == 'direct') {
			$lien = '<a href="'.dol_buildpath('/factory/product/', 1).'direct.php?id='.$id.'">';
			$lienfin='</a>';
		} else {
			$lien = '<a href="'.DOL_URL_ROOT.'/product/fiche.php?id='.$id.'">';
			$lienfin='</a>';
		}

		$tmpproduct = new Product($this->db);
		$tmpproduct->fetch($id);
		$productref=$tmpproduct->ref;

		$newref=$productref;
		if ($maxlength) 
			$newref=dol_trunc($newref, $maxlength, 'middle');

		if ($withpicto ) {
			if ($tmpproduct->type == 0) {
				if ($tmpproduct->finished == 1)
					$result.=($lien.img_object(
						$langs->trans("ShowManufacturedProduct").' '.$productref, 'factory@factory'
					).$lienfin.' ');
				else
					$result.=($lien.img_object(
									$langs->trans("ShowProduct").' '.$productref, 'product'
					).$lienfin.' ');
			} else
				$result.=($lien.img_object(
								$langs->trans("ShowService").' '.$productref, 'service'
				).$lienfin.' ');
		}
		$result.=$lien.$newref.$lienfin;
		return $result;
	}

	/**
	 *	Return clicable link of object (with eventually picto)
	 *
	 *	@param		int		$withpicto		Add picto into link
	 *	@param		string	$option			Where point the link
	 *	@return		string					String with URL
	 */
	function PopupProduct($id, $idsecond="")
	{
		global $conf;

		$tmpproduct = new Product($this->db);
		$result='';
		$tmpproduct->fetch($id);
		if ($tmpproduct->is_photo_available($conf->product->multidir_output [$tmpproduct->entity])) {
			// pour gérer le cas d'une même photo sur un meme document
			if ($idsecond)
				$id.="-".$idsecond;
			$result='<a id="trigger'.$id.'" >'.img_down().'</a>';
			$result.='<div id="pop-up'.$id.'"';
			$result.='style="display: none;  position: absolute;   ';
			$result.='padding: 2px;  background: #eeeeee;  color: #000000;  border: 1px solid #1a1a1a;" >';
			
			$result.=$tmpproduct->show_photos($conf->product->multidir_output [$tmpproduct->entity], 1, 1, 0, 0, 0, 80);
			$result.='</div>';
			$result.='<script>$(function() {';
			$result.="$('a#trigger".$id."').hover(function() {";
			$result.="$('div#pop-up".$id."').show();";
			$result.="},";
			$result.="function() {";
			$result.="$('div#pop-up".$id."').hide();";
			$result.="});   });";
			$result.='</script>';
		}
		return $result;
	}


	/**
	 *	Return clicable link of object (with eventually picto)
	 *
	 *	@param		int		$withpicto		Add picto into link
	 *	@param		string	$option			Where point the link
	 *	@param		int		$maxlength		Maxlength of ref
	 *	@return		string					String with URL
	 */
	function getUrlStock($id, $withpicto=0, $nbStock=0)
	{
		global $langs;

		$tmpproduct = new Product($this->db);
		$result='';
		$tmpproduct->fetch($id);
		$title="";
		if (! empty($conf->productbatch->enabled)) {
			$tmpproduct->load_stock();
			if ($tmpproduct->hasbatch()) {
				$details= $tmpproduct->stock_warehouse[1]->detail_batch;
				foreach ($details as $pdluo) {
					$title.=$langs->trans("BatchLot")." : " .$pdluo->batch;
					$title.=' ('.$pdluo->qty .")<br>";
				}
			}
		}

		$lien = '<a title="'.$title.'" href="'.DOL_URL_ROOT.'/product/stock/product.php?id='.$id.'">';
		$lienfin='</a>';

		$result.=$lien.$nbStock.$lienfin;
		return $result;
	}


	/**
	 *  Return childs of product with if fk_parent
	 *
	 * 	@param		int		$fk_parent	Id of product to search childs of
	 *  @return	 array	   		Prod
	 */
	function getChildsArbo($fk_parent, $fk_factory =0, $fk_bom=0, $maxlevel=0, $sortfield="", $sortorder="")
	{
		global $conf;
		if (!empty($conf->global->RESTOCK_RECURSIVITY_DEEP))
			$maxRecursityDeep=$conf->global->RESTOCK_RECURSIVITY_DEEP;
		else
			$maxRecursityDeep=42;

		// si on est pas dans trop loin dans la récursivité, 
		if ($maxlevel > $maxRecursityDeep) {
			global $langs;
			print $langs->trans("RecursivityLimitReached", $fk_parent." - ".$fk_factory." - ".$maxlevel)." <br>";
			return array();
		}
		else
			$maxlevel++;

		if ($fk_parent > 0) {
			if ($fk_bom > 0) {
				$sql = "SELECT p.rowid, p.label as label, p.fk_product_type,";
				$sql.= " bl.qty as qty, 0 as pmp, 0 as price, bl.fk_product as id,";
				// on ne gère pas la reprise de quantité globale du bom pour le moment
				$sql.= " 0 as globalqty, bl.description as description, bl.position as ordercomponent";
				$sql.= " , 0 as qty_product_ok";
				$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
				$sql.= " , ".MAIN_DB_PREFIX."bom_bomline as bl";
				$sql.= " WHERE p.rowid = bl.fk_product";
				$sql.= " AND bl.fk_bom = ".$this->fk_bom;
				if ($sortfield=="")
					$sql.= " ORDER BY bl.position, p.ref";
				else
					$sql.= " ORDER BY ".$sortfield." ".$sortorder;
			} else {
				// on est sur la composition OF du produit
				$sql = "SELECT p.rowid, p.label as label, p.fk_product_type,";
				$sql.= " pf.qty as qty, pf.pmp as pmp, pf.price as price, pf.fk_product_children as id,";
				$sql.= " pf.globalqty as globalqty, pf.description as description, pf.ordercomponent";
				$sql.= " , 0 as qty_product_ok";
				$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
				$sql.= " , ".MAIN_DB_PREFIX."product_factory as pf";
				$sql.= " WHERE p.rowid = pf.fk_product_children";
				$sql.= " AND pf.fk_product_father = ".$fk_parent;
				if ($sortfield=="")
					$sql.= " ORDER BY pf.ordercomponent, p.ref";
				else
				$sql.= " ORDER BY ".$sortfield." ".$sortorder;
			}
		} else {
			// on est sur un OF
			$sql = "SELECT p.rowid, p.label as label, p.fk_product_type,";
			$sql.= " fd.qty_unit as qty, fd.pmp as pmp, fd.price as price, fd.fk_product as id,";
			$sql.= " fd.globalqty as globalqty, fd.description as description, 1 as ordercomponent";
			$sql.= " , fd.qty_product_ok";
			$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
			$sql.= ", ".MAIN_DB_PREFIX."factorydet as fd";
			$sql.= " WHERE p.rowid = fd.fk_product";
			$sql.= " AND fd.fk_factory = ".$fk_factory ;
			$sql.= " ORDER by p.ref";
		}

		$res  = $this->db->query($sql);
		if ($res) {
			$prods = array();
			while ($rec = $this->db->fetch_array($res)) {
				$prods[$rec['rowid']]= array(
								0=>$rec['id'],					// product id
								1=>$rec['qty'],					// qty need
								2=>$rec['fk_product_type'],
								3=>$this->db->escape($rec['label']),
								4=>$rec['pmp'],
								5=>$rec['price'],
								6=>$rec['globalqty'],
								7=>$rec['description'],
								8=>$rec['ordercomponent'],
								9=>array(),						// pour stocker les enfants sans fiche le basard
								10=>$rec['qty_product_ok']
							);
				$listofchilds=$this->getChildsArbo($rec['id'], 0, 0, $maxlevel);
				foreach ($listofchilds as $keyChild => $valueChild)
					$prods[$rec['rowid']][9] = $valueChild;  // on stock les enfants dans le 6e tableau
			}

			// seulement sur la composition de l'of
			if ($fk_parent > 0 && $fk_bom == 0) {
				// si il y a une mise à jour de la composition de l'OF, on met à jour le tableau
				if (!empty($conf->global->MAIN_MODULE_BOMGENERATOR)) {
					// si il y a des produits modifiées pour cette OF, on prend celui-ci
					dol_include_once("/bomgenerator/class/bomgenerator.class.php");
					$factorygenerator = new factorygenerator($this->db);
					$i=0;
					foreach ($prods as $key => $value) {
						$arrayProductInfo = $factorygenerator->get_productchanged($fk_parent, $i);
						//var_dump($arrayProductInfo);
						if (is_array($arrayProductInfo)) {
							// on récupère les infos du nouveau produit
							$prods[$key][0] = $arrayProductInfo['fk_product'];
							$prods[$key][3] = $arrayProductInfo['label'];
							$prods[$key][4] = $arrayProductInfo['pmp'];
							$prods[$key][5] = $arrayProductInfo['price'];
						}
						$i++;
					}
					//var_dump($prods);
				}
			}
			return $prods;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

	/**
	 *  Return return true of false depending of product a composed or not
	 * 	@param		int		$fk_parent	Id of product to search childs of
	 *  @return	 array	   		Prod
	 */
	function is_FactoryProduct($fk_product)
	{
		// cas des produits saisies libres
		if ($fk_product == 0)
			return 0;
		
		$sql = "SELECT pf.fk_product_father";
		$sql.= " FROM ".MAIN_DB_PREFIX."product_factory as pf";
		$sql.= " WHERE pf.fk_product_father = ".$fk_product;

		$res  = $this->db->query($sql);
		if ($res) {
			if ($this->db->num_rows($res) > 0)
				return 1;
			else
				return 0;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

	// retourne la liste des produits qui peuvent créer un OF
	function getListProductWithComposition() {
		$sql = "SELECT distinct p.rowid, p.ref as ref, p.label as label";
		$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
		$sql.= " , ".MAIN_DB_PREFIX."product_factory as pf";
		$sql.= " WHERE pf.fk_product_father  = p.rowid";
		$sql.= " ORDER BY p.Label";
		$res  = $this->db->query($sql);
		if ($res) {
			$prods = array();
			while ($rec = $this->db->fetch_array($res)) {
				$prods[$rec['rowid']]= $rec['ref']." - ".$rec['label'];
			}
			return $prods;
		}
	}

	// récupère la liste des produits d'un of
	function getChildsOF($fk_factory, $sortField="", $sortOrder="", $search_categ=0, $search_fourn=0)
	{
		$sql = "SELECT fd.rowid as rowid, fd.fk_product as id, p.label as label, ";
		$sql.= " fd.qty_unit as qtyunit, fd.qty_planned as qtyplanned,";
		$sql.= " fd.qty_used as qtyused, fd.qty_deleted as qtydeleted, fd.globalqty, fd.description,";
		$sql.= " fd.fk_mvtstockplanned as mvtstockplanned, fd.fk_mvtstockused as mvtstockused,";
		$sql.= " fd.pmp as pmp, fd.price as price, p.ref, p.fk_product_type, fd.ordercomponent";
		$sql.= " , fd.qty_product_ok";
		$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
		$sql.= " , ".MAIN_DB_PREFIX."factorydet as fd";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."factorydet_extrafields as ef ON fd.rowid = ef.fk_object";
		if ($search_fourn > 0)
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product_fournisseur_price as pfp ON fd.fk_product = pfp.fk_product";
		// We'll need this table joined to the select in order to filter by categ
		if ($search_categ > 0) 
			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX."categorie_product as cp ON fd.fk_product = cp.fk_product"; 

		$sql.= " WHERE p.rowid = fd.fk_product";
		$sql.= " AND fd.fk_factory = ".$fk_factory;

		if ($search_fourn > 0)   $sql.= " AND pfp.fk_soc = ".$search_fourn;
		if ($search_categ > 0)   $sql.= " AND cp.fk_categorie = ".$search_categ;
		
		if ($sortField=="")
			$sql.= " ORDER BY fd.ordercomponent";
		else
			$sql.= " ORDER BY ".$sortField." ".$sortOrder;

		$res  = $this->db->query($sql);
		if ($res) {
			$prods = array();
			while ($rec = $this->db->fetch_array($res)) {
				
				$prods[]= array(
						'rowid'=>$rec['rowid'],						// rowid of the line
						'id'=>$rec['id'],							// Id product
						'refproduct'=>$rec['ref'],					// label product
						'label'=>$rec['label'],						// label product
						'pmp'=>$rec['pmp'],							// pmp of the product
						'price'=>$rec['price'],						// price of the product
						'nb'=>$rec['qtyunit'],						// Nb of units that compose parent product
						'globalqty'=>$rec['globalqty'],				// 
						'description'=>$rec['description'],			// 
						'qtyused'=>$rec['qtyused'],					// 
						'qtydeleted'=>$rec['qtydeleted'],			// 
						'qtyplanned'=>$rec['qtyplanned'],			// 
						'mvtstockplanned'=>$rec['mvtstockplanned'],	// 
						'mvtstockused'=>$rec['mvtstockused'],		// 
						'type'=>$rec['fk_product_type'],			// 
						'ordercomponent'=>$rec['ordercomponent'],
						'qty_product_ok'=>$rec['qty_product_ok'],
						'composed'=>$this->getChildsArbo($rec['id'])	// determine si le produit est un composant (>0 si c'est le cas)
				);
				// remarque, le getchildsarbo étant récursif, on a pas besoin de faire de récursivité ici
				
				
			}
			
			
			return $prods;
		}
		
		dol_print_error($this->db);
		return -1;
	}


	function getChildsTasks($fk_project, $fk_task)
	{
		$sql = "SELECT ptd.fk_product as id, p.label as label, p.fk_product_type, ";
		$sql.= " pt.rowid as idtask, pt.ref as reftask, p.ref as refproduct, ";
		$sql.= " ptd.qty_planned as qtyplanned, ptd.qty_used as qtyused, ";
		$sql.= " ptd.qty_deleted as qtydeleted, ptd.pmp as pmp, ptd.price as price";
		$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
		$sql.= ", ".MAIN_DB_PREFIX."projet_taskdet as ptd";
		$sql.= ", ".MAIN_DB_PREFIX."projet_task as pt";
		$sql.= " WHERE p.rowid = ptd.fk_product";
		$sql.= " AND pt.rowid = ptd.fk_task";
		if ($fk_task > 0)
			$sql.= " AND ptd.fk_task = ".$fk_task;
		else
			$sql.= " AND pt.fk_projet = ".$fk_project;
		$sql.= " ORDER BY p.ref, pt.ref";

		$res  = $this->db->query($sql);
		if ($res) {
			$prods = array();
			while ($rec = $this->db->fetch_array($res)) {
				$prods[]= array(
					'id'=>$rec['id'],					// Id product
					'refproduct'=>$rec['refproduct'],	// ref of  product
					'label'=>$rec['label'],				// label of product
					'idtask'=>$rec['idtask'],			// ref of task
					'reftask'=>$rec['reftask'],			// ref of task
					'pmp'=>$rec['pmp'],					// pmp of the product
					'price'=>$rec['price'],				// price of the product
					'nb'=>1,							// Nb of units that compose parent product
					'qtyplanned'=>$rec['qtyplanned'],	// Nb of units planned to use on build
					'qtyused'=>$rec['qtyused'],			// Nb of units realy used on build
					'qtydeleted'=>$rec['qtydeleted'],	// Nb of units deleted during ther build
					'type'=>$rec['fk_product_type']		// type of product (materiel or service)
				);
			}

			return $prods;
		}
		dol_print_error($this->db);
		return -1;
	}

	/**
	 *  Return childs of prodcut with if fk_parent
	 *
	 * 	@param		int		$fk_parent	Id of product to search childs of
	 *  @return	 array	   		Prod
	 */
	function cloneFromVirtual()
	{
		$sql = "SELECT fk_product_fils, qty";
		$sql.= " FROM ".MAIN_DB_PREFIX."product_association as pa";
		$sql.= " WHERE pa.fk_product_pere = ".$this->id;

		$res  = $this->db->query($sql);
		if ($res) {
			while ($rec = $this->db->fetch_array($res)) {
				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'product_factory (fk_product_father, fk_product_children, qty)';
				$sql .= ' VALUES ('.$this->id.','.$rec['fk_product_fils'].','.$rec['qty'].')';
				if (! $this->db->query($sql)) {
					dol_print_error($this->db);
					return -1;
				}
			}
			
			// à la fin du transfert on supprime le param�trage du produit virtuel
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."product_association as pa";
			$sql.= " WHERE pa.fk_product_pere = ".$this->id;
			$res  = $this->db->query($sql);
			return 0;
		}
		dol_print_error($this->db);
		return -1;
	}

	function cloneFromVariant($product_pere)
	{
		$sql = "SELECT fk_product_children, qty";
		$sql.= " FROM ".MAIN_DB_PREFIX."product_factory as pf";
		$sql.= " WHERE pf.fk_product_father = ".$product_pere;

		$res  = $this->db->query($sql);
		if ($res) {
			// on supprime le paramétrage précédent
			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'product_factory';
			$sql .= ' WHERE fk_product_father ='.$this->id;
			$this->db->query($sql);

			while ($rec = $this->db->fetch_array($res)) {
				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'product_factory (fk_product_father, fk_product_children, qty)';
				$sql .= ' VALUES ('.$this->id.','.$rec['fk_product_children'].','.$rec['qty'].')';
				if (! $this->db->query($sql)) {
					dol_print_error($this->db);
					return -1;
				}
			}
			return 0;
		}
		dol_print_error($this->db);
		return -1;
	}

	/**
	 *	Load an object from its id and create a new one in database
	 *
	 *	@param		int			$socid			Id of thirdparty
	 *	@return		int							New id of clone
	 */
	function createFromClone($productid=0, $qty_planned=0, $fk_entrepot=0 )
	{
		global $conf, $user, $langs, $hookmanager;

		$error=0;

		$this->context['createfromclone'] = 'createfromclone';
		$this->db->begin();

		// get extrafields so they will be clone
		//foreach ($this->lines as $line)
		//	$line->fetch_optionals($line->rowid);

		// Load source object
		$objFrom = dol_clone($this);

		// Change productid if needed
		if (!  $productid != $this->id) {
			$objproduct = new Product($this->db);

			if ($objproduct->fetch($productid)>0) {
				$this->fk_product	= $productid;
				$this->id			= $productid;
			}
		}

		// Change productid if needed
		if (!  $qty_planned != $this->qty_planned)
			$this->qty_planned 	= $qty_planned;
		
		if (!  $fk_entrepot != $this->fk_entrepot)
			$this->fk_entrepot 	= $fk_entrepot;
		
		$this->fk_statut=0;
		
		// Clear fields
		$this->user_author_id	 = $user->id;
		$this->user_valid		 = '';
		$this->date_creation	  = '';
		$this->date_validation	= '';
		$this->ref_client		 = '';
		
		// Set ref
		dol_include_once("/factory/core/modules/factory/".$conf->global->FACTORY_ADDON.'.php');
		$obj = $conf->global->FACTORY_ADDON;
		$modFactory = new $obj($db);
		$this->ref = $modFactory->getNextValue($objproduct, $this);
		
		// Create clone
		$result=$this->createof();
		$cloneid=$result;
		if ($result < 0) $error++;

		if (! $error) {
			// Hook of thirdparty module
			if (is_object($hookmanager)) {
				$parameters=array('objFrom'=>$objFrom);
				$action='';
				// Note that $action and $object may have been modified by some hooks
				$reshook=$hookmanager->executeHooks('createFrom', $parameters, $this, $action);
				if ($reshook < 0) $error++;
			}

			// Call trigger
			if ($conf->global->MAIN_VERSION_LAST_UPGRADE > '3.6.0') {
				$restrigger=$this->call_trigger('FACTORY_CLONE', $user);
				if ($restrigger < 0) $error++;
			} else {
				include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('FACTORY_CLONE', $this, $user, $langs, $conf);
				if ($result < 0) {
					$error++; $this->errors=$interface->errors;
				}
			}
		}

		unset($this->context['createfromclone']);

		// End
		if (! $error) {
			$this->db->commit();
			return $cloneid;
		}
		
		 $this->db->rollback();
		return -1;
	}

	/**
	 *  Return childs of product with if fk_parent
	 *
	 * 	@param		$mode		0 : on modifie le prix de la composition  / 1 : on modifie le prix de l'of
	 	
	 *  @return	 array	   		Prod
	 */
	function getdefaultprice($mode=0)
	{
		global $conf;
		
		$sql = "SELECT p.rowid, p.pmp, p.price, p.fk_product_type";
		$sql.= ", p.cost_price ";
		if ($mode == 0) {
			$sql.= " FROM ".MAIN_DB_PREFIX."product_factory as pf, ".MAIN_DB_PREFIX."product as p";
			$sql.= " WHERE pf.fk_product_children = p.rowid";
			$sql.= " and pf.fk_product_father = ".$this->id;
		} else {
			$sql.= " FROM ".MAIN_DB_PREFIX."factorydet as fd, ".MAIN_DB_PREFIX."product as p";
			$sql.= " WHERE fd.fk_product = p.rowid";
			$sql.= " and fd.fk_factory = ".$this->id;
		}
		$res  = $this->db->query($sql);
		if ($res) {
			// on boucle sur la liste des composants
			while ($rec = $this->db->fetch_array($res)) {
				$pmprice = 'null';
				if ($rec['fk_product_type'] == 0)
					$selpricemode = $conf->global->FACTORY_COMPONENT_BUYINGPRICE;
				else
					$selpricemode = $conf->global->FACTORY_COMPONENT_BUYINGPRICESERVICE;
				
				switch($selpricemode) {
					case 'pmpprice':
					case '':
						$pmprice = $rec['pmp']?price2num($rec['pmp']):'null';
						break;
					case 'costprice':
						$pmprice = $rec['cost_price']?price2num($rec['cost_price']):'null';
						break;
					case 'fournishmore':
					case 'fournishless':
						// pour gérer le cas des produits avec des quantités différentes de 1
						if ($selpricemode == 'fournishmore')
							$sql = "SELECT quantity, MAX(price) AS pricefourn";
						else	// récup du prix fournisseur le plus haut
							$sql = "SELECT quantity, MIN(price) AS pricefourn";
						$sql.= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price ";
						$sql.= " WHERE fk_product=".$rec['rowid'];
						$sql.= " GROUP BY quantity";
						$sql.= " ORDER BY quantity";
						//$pmprice = null;
						$resfournishprice  = $this->db->query($sql);
						if ($resfournishprice) {
							$objFournPrice = $this->db->fetch_object($resfournishprice);
							// si il y a un prix fournisseur on le divise par sa quantité pour avoir le prix unitaire
							if ($objFournPrice->pricefourn)
								$pmprice = price2num($objFournPrice->pricefourn / $objFournPrice->quantity);
						}
						break;
				}

				if ($mode == 0) {
					$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_factory';
					$sql .= ' SET pmp= '.$pmprice;
					$sql .= ' , price='.($rec['price']?price2num($rec['price']):'null');
					$sql .= ' where fk_product_father= '.$this->id ;
					$sql .= ' and fk_product_children= '.$rec['rowid'] ;
				} else {
					$sql = 'UPDATE '.MAIN_DB_PREFIX.'factorydet';
					$sql .= ' SET pmp= '.$pmprice;
					$sql .= ' , price='.($rec['price']?price2num($rec['price']):'null');
					$sql .= ' where fk_factory= '.$this->id ;
					$sql .= ' and fk_product = '.$rec['rowid'] ;
				}
				
				if (! $this->db->query($sql)) {
					dol_print_error($this->db);
					return -1;
				}
			}
			return 0;
		} else {
			dol_print_error($this->db);
			return -1;
		}

	}


	function getdefaultpricetask($fk_task)
	{
		$sql = "SELECT p.rowid, p.pmp, p.price, p.fk_product_type";
		$sql.= ", p.cost_price ";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_taskdet as ptd, ".MAIN_DB_PREFIX."product as p";
		$sql.= " WHERE ptd.fk_product = p.rowid";
		$sql.= " and ptd.fk_task = ".$fk_task;

		$res  = $this->db->query($sql);
		if ($res) {
			while ($rec = $this->db->fetch_array($res)) {
				$pmprice = 'null';
				if ($rec['fk_product_type'] == 0)
					$selpricemode = $conf->global->FACTORY_COMPONENT_BUYINGPRICE;
				else
					$selpricemode = $conf->global->FACTORY_COMPONENT_BUYINGPRICESERVICE;

				switch($selpricemode) {
					case 'pmpprice':
					case '':
						$pmprice = $rec['pmp']?price2num($rec['pmp']):'null';
						break;
					case 'costprice':
						$pmprice = $rec['cost_price']?price2num($rec['cost_price']):'null';
						break;
					case 'fournishmore':
					case 'fournishless':
						// récup du prix fournisseur le plus haut
						if ($selpricemode == 'fournishmore')
							$sql = "SELECT MAX(price) AS pricefourn";
						else	// récup du prix fournisseur le plus bas
							$sql = "SELECT MIN(price) AS pricefourn";
						$sql.= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price ";
						$sql.= " WHERE quantity=1 AND fk_product=".$rec['rowid'];
						$resfournishprice  = $this->db->query($sql);
						if ($resfournishprice) {
							$objfournishprice = $this->db->fetch_object($resfournishprice);
							$pmprice = $objfournishprice->pricefourn ?price2num($objfournishprice->pricefourn):'null';
						}
						break;
				}

				$sql = 'UPDATE '.MAIN_DB_PREFIX.'projet_taskdet';
				$sql .= ' SET pmp= '.$pmprice;
				$sql .= ' where fk_product='.$rec['rowid'];
				$sql .= ' and fk_task='. $fk_task;

				if (! $this->db->query($sql)) {
					dol_print_error($this->db);
					return -1;
				}
			}
			return 0;
		}
		dol_print_error($this->db);
		return -1;
	}

	/**
	 *  Return childs of product with if fk_parent
	 *
	 * 	@param		int		$fk_parent	Id of product to search childs of
	 *  @return	 array	   		Prod
	 */
	function updatefactoryprices($fk_product_children, $pmp=0, $price=0)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_factory';
		$sql .= ' SET pmp= '.price2num($pmp).', price='.price2num($price);
		$sql .= ' where fk_product_father= '.$this->id;
		$sql .= ' and fk_product_children= '.$fk_product_children ;
		//print $sql."<br>";
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		}
	}

	function updateSubcontracting($user) {
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'factory';
		$sql .= ' SET fk_soc= '.($this->fk_soc>0?$this->fk_soc:"null");
		$sql .= ' , ref_supplier= '.(!empty($this->ref_supplier)?'"'.$this->ref_supplier.'"':'null');
		$sql .= " , delivery_date = ".($this->delivery_date ? "'".$this->db->idate($this->delivery_date)."'" :'null');
		$sql .= " , date_start_made = now()";
		$sql .= " , fk_user_commande = ".$user->id;
		$sql .= ' WHERE rowid = '.$this->id;
		
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		} else {
			// on récupère la ligne de produit fabriqué pour la suite
			$this->fetch_lines();
			// pour actualiser le statut de OF
			$this->set_statut();
		}
	}

	// on cloture l'OF en fonctionne des infos de la commande fournisseur
	function closeSubcontractingOF($cmdFournId) 
	{
		//récupération des infos liée à la commande fournisseur
		$sql = 'SELECT max(cfd.datec) as dateDispatch,';
		$sql .= ' sum(cfd.qty) as qtyDispatch';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'commande_fournisseur_dispatch as cfd';
		$sql .= ' WHERE fk_commande ='.$cmdFournId;
		$sql .= ' AND fk_product ='.$this->fk_product;
		
		$infoDispatch  = $this->db->query($sql);
		if ($infoDispatch) {
			$objDispatch = $this->db->fetch_object($infoDispatch);
			// on récupère la quantité réellement réceptionnées
			$qtyDispatch = $objDispatch->qtyDispatch;
			// et la date de réception de la commande
			$dateDispatch = $objDispatch->dateDispatch;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'factory';
		$sql .= " SET qty_made =".$qtyDispatch;
		$sql .= " , date_end_made ='".$dateDispatch."'";
		$sql .= " , fk_statut = 2";
		$sql .= ' WHERE rowid = '.$this->id;
		//print $sql;
		$this->db->query($sql);


	}


	function controlingInfos() {
		// si l'OF est déjà terminé
		if ($this->fk_statut < 2 ) {
			// par défaut on est bon 
			$retValue='<font color="green"><b>'."OK".'</b></font>';
			$product_static = new product($this->db);
			// on boucle sur les composant de l'of
			$componentArray = $this->getChildsOF($this->id);
			foreach($componentArray as $key => $value) {
				if (empty($value['type']) || $value['type'] ==0) {
					//var_dump($value);
					$product_static->fetch($value['id']);
					$product_static->load_stock();
					$stockEntrepot =0;
					if (!empty($product_static->stock_warehouse))
						$stockEntrepot = $product_static->stock_warehouse[$this->fk_entrepot]->real;
					$qteNeed = $value['qtyplanned'];
					$qtyRestante = $stockEntrepot - $qteNeed;
					if ($qtyRestante < 0) {
						// pas assez en entrepot on vérifie au global
						$qtyRestanteGlobal = $product_static->stock_reel - $qteNeed;
						if ($qtyRestanteGlobal<0) {
							$retValue='<font color="red"><b>'."KO".'</b></font>';
							// plus la peine on sort
							break;
						} else {
							$retValue='<font color="orange"><b>'."KO".'</b></font>';
						}
					}
				}
			}
		} else
			$retValue=''; // on affiche rien alors
		return $retValue;
	}


	/**
	 *  Return childs of product with if fk_parent
	 *
	 * 	@param		int		$fk_product	Id of product to search childs of
	 *  @return	 array	   		Prod
	 */
	function updateOFprices($fk_product, $pmp=0, $price=0)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'factorydet';
		$sql .= ' SET pmp= '.price2num($pmp).', price='.price2num($price);
		$sql .= ' where fk_factory= '.$this->id;
		$sql .= ' and fk_product= '.$fk_product;
		//print $sql."<br>";
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		}
	}


	/**
	 *  Return childs of prodcut with if fk_parent
	 *
	 * 	@param		int		$fk_parent	Id of product to search childs of
	 *  @return	 array	   		Prod
	 */
	function updatefactorytaskprices($fk_task, $fk_product, $pmp, $price)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'projet_taskdet';
		$sql .= ' SET pmp='.($pmp?$pmp:'null').', price='.($price?$price:'null');
		$sql .= ' where fk_task= '.$fk_task;
		$sql .= ' and fk_product= '.$fk_product ;
		//print $sql."<br>";
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		}
	}

	/**
	 *  Return childs of product with if fk_parent
	 *
	 * 	@param		int		$fk_parent	Id of product to search childs of
	 *  @return	 array	   		Prod
	 */
	function updatefactorytaskqty($fk_task, $fk_product, $qtyused, $qtydeleted)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'projet_taskdet';
		$sql .= ' SET qty_used='.($qtyused ? $qtyused : 'null').', qty_deleted='.($qtydeleted ? $qtydeleted : 'null');
		$sql .= ' where fk_task= '.$fk_task;
		$sql .= ' and fk_product= '.$fk_product ;
		//print $sql."<br>";
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		}
	}

	// fonction laissée pour assurer la compatibilité avec equipement V2
	function get_equipement_linked($equipementid)
	{
		$sql = "SELECT fk_factory";
		$sql.= " FROM ".MAIN_DB_PREFIX."equipement_factory as ef";
		$sql.= " WHERE ef.fk_equipement = ".$equipementid;

		$res = $this->db->query($sql);
		if ($res) {
			$obj = $this->db->fetch_object($res);
			return $obj->fk_factory;
		}
		return 0;
	}

	/**
	 * 	Return tree of all subproducts for product. Tree contains id, name and quantity.
	 * 	Set this->sousprods
	 *
	 *  @return		void
	 */
	function get_sousproduits_factory_arbo($factoryid)
	{
		$this->sousprods["fab"][0]=$this->id;
		$sql = "SELECT fd.fk_product as id, fd.qty_unit, p.label, p.fk_product_type, fd.qty_product_ok";
		$sql.= " FROM ".MAIN_DB_PREFIX."factorydet as fd";
		$sql.= " , ".MAIN_DB_PREFIX."product as p";
		$sql.= " WHERE fd.fk_product = p.rowid";
		$sql.= " AND fd.fk_factory = ".$factoryid;
		//print $sql;
		$resql=$this->db->query($sql);
		if ($resql) {
			$nump = $this->db->num_rows($resql);
			if ($nump) {
				$i = 0;
				while ($i < $nump) {
					$obj = $this->db->fetch_object($resql);

					$this->sousprods["fab"][$obj->id]= array(0=>$obj->id,
											1=>$obj->qty_unit,
											2=>$obj->fk_product_type,
											3=>$this->db->escape($obj->label),
											4=>$obj->qty_product_ok
								);
				 	$i++;
				}
			}
		}
	}


	function get_sousproduits_arbo($sortfield="", $sortorder="")
	{
		$parent = $this->getParent();
		foreach ($parent as $key => $value) {
			$listofchilds=$this->getChildsArbo($value[0],0, 0, 0, $sortfield, $sortorder);
			foreach ($listofchilds as $keyChild => $valueChild)
				$parent[$key][$keyChild] = $valueChild;
		}

		foreach ($parent as $key => $value)
			$this->sousprods[$key] = $value;
	}
	
	// mis à jour du statut de l'OF selon les dates associé
	function set_statut()
	{
		$this->fk_statut =0;						// brouillon
		//$this->date_start_made = $datestartmade;
		if (!empty($this->date_start_made)) 	// validé
			$this->fk_statut  = 1;
		if (!empty($this->delivery_date))		// OF sous-traité
			$this->fk_statut = 4;
		if (!empty($this->date_end_made)) {
			if ($this->qty_made > 0)
				$this->fk_statut = 2;			// terminé
			else 
				$this->fk_statut = 9;			// Of annulé
		}
		$sql = "UPDATE ".MAIN_DB_PREFIX."factory";
		$sql.= " SET fk_statut =".$this->fk_statut;
		$sql.= " WHERE rowid = ".$this->id;

		$this->db->query($sql);

	}

	function set_datestartmade($user, $datestartmade)
	{
		global $conf, $langs;

		// c'est lors de la première validation que l'on effectue les mouvements de stocks des composants
		if ($user->rights->factory->creer) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."factory";
			$sql.= " SET date_start_made = ".($datestartmade ? "'".$this->db->idate($datestartmade)."'" :'null');
			$sql.= " WHERE rowid = ".$this->id;
			//print $sql;
			if ($this->db->query($sql)) {
				$this->date_start_made = $datestartmade;
				
				// pour actualiser le statut de OF
				$this->set_statut();

				// on récupère les composants et on mouvemente le stock si cela n'est pas encore fait (idmvt à 0)
				$sql = "select * from ".MAIN_DB_PREFIX."factorydet where fk_factory=".$this->id;
				$sql.= " and fk_mvtstockplanned=0";
		
				$res  = $this->db->query($sql);
				if ($res) {
					require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
					$mouvP = new MouvementStock($this->db);
					// pour conserver l'origine du mouvement (avant la V15)
					$mouvP->origin = new Factory($this->db);
					$mouvP->origin->id = $this->id;
					// V15 on passe par l'élément direct
					$mouvP->origin_type = $mouvP->origin->element; // 'factory'
					$mouvP->origin_id = $mouvP->origin->id;
					
					while ($rec = $this->db->fetch_array($res)) {
						$idmv=$mouvP->livraison(
										$user, $rec['fk_product'], $this->fk_entrepot, 
										$rec['qty_planned'], $rec['price'], 
										$langs->trans("UsedforFactory", $this->ref), $this->date_start_made
						);
						// on indique que l'on a mouvementé le produit
						if ($idmv > 0 ) {
							// on mémorise que l'on a fait le mouvement de stock (pour ne pas le faire plusieurs fois)
							$sql = "update ".MAIN_DB_PREFIX."factorydet set fk_mvtstockplanned=".$idmv;
							$sql.= " where rowid=".$rec['rowid'];
							$this->db->query($sql);
						}
					}
				}
				return 1;
			} else {
				$this->error=$this->db->error();
				dol_syslog(get_class($this)."::set_datestartmade Erreur SQL ".$this->error, LOG_ERR);
				return -1;
			}
		}
	}

	function set_datestartplanned($user, $datestartplanned)
	{
		global $conf;

		if ($user->rights->factory->creer) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."factory ";
			$sql.= " SET date_start_planned = ".($datestartplanned? "'".$this->db->idate($datestartplanned)."'" :'null');
			$sql.= " WHERE rowid = ".$this->id;

			if ($this->db->query($sql)) {
				$this->date_start_planned = $datestartplanned;
				return 1;
			} else {
				$this->error=$this->db->error();
				dol_syslog(get_class($this)."::set_datestartplanned Erreur SQL ".$this->error, LOG_ERR);
				return -1;
			}
		}
	}
	
	function set_dateendplanned($user, $dateendplanned)
	{
		global $conf;

		if ($user->rights->factory->creer) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."factory ";
			$sql.= " SET date_end_planned = ".($dateendplanned? "'".$this->db->idate($dateendplanned)."'" :'null');
			$sql.= " WHERE rowid = ".$this->id;

			if ($this->db->query($sql)) {
				$this->date_end_planned = $dateendplanned;
				return 1;
			} else {
				$this->error=$this->db->error();
				dol_syslog(get_class($this)."::set_dateendplanned Erreur SQL ".$this->error, LOG_ERR);
				return -1;
			}
		}
	}
	
	function set_durationplanned($user, $durationplanned)
	{
		global $conf;

		if ($user->rights->factory->creer) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."factory ";
			$sql.= " SET duration_planned = ".($durationplanned ? $durationplanned :'null');
			$sql.= " WHERE rowid = ".$this->id;

			if ($this->db->query($sql)) {
				$this->duration_planned = $durationplanned;
				return 1;
			} else {
				$this->error=$this->db->error();
				dol_syslog(get_class($this)."::set_durationplanned Erreur SQL ".$this->error, LOG_ERR);
				return -1;
			}
		}
	}
	function set_description($user, $description)
	{
		global $conf;

		if ($user->rights->factory->creer) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."factory ";
			$sql.= " SET description = '".$this->db->escape($description)."'";
			$sql.= " WHERE rowid = ".$this->id;

			if ($this->db->query($sql)) {
				$this->description = $description;
				return 1;
			} else {
				$this->error=$this->db->error();
				dol_syslog(get_class($this)."::set_description Erreur SQL ".$this->error, LOG_ERR);
				return -1;
			}
		}
	}
	function set_entrepot($user, $fk_entrepot)
	{
		global $conf;

		if ($user->rights->factory->creer) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."factory ";
			$sql.= " SET fk_entrepot = ".$fk_entrepot;
			$sql.= " WHERE rowid = ".$this->id;

			if ($this->db->query($sql)) {
				$this->fk_entrepot = $fk_entrepot;
				return 1;
			} else {
				$this->error=$this->db->error();
				dol_syslog(get_class($this)."::set_description Erreur SQL ".$this->error, LOG_ERR);
				return -1;
			}
		}
	}
	function set_qtyplanned($user, $qty_planned)
	{
		global $conf;

		if ($user->rights->factory->creer) {
			// on récupère le ratio avec la quantité précédente
			// pour mettre à jour les composants nécessaires
			$ratioUpdate = $qty_planned / $this->qty_planned;
			
			$sql = "UPDATE ".MAIN_DB_PREFIX."factory ";
			$sql.= " SET qty_planned = ".($qty_planned?$qty_planned:'null');
			$sql.= " WHERE rowid = ".$this->id;

			if ($this->db->query($sql)) {
				$this->qty_planned = $qty_planned;

				$componentArray = $this->getChildsOF($this->id);
				foreach($componentArray as $component) {
					// si ce n'est pas une quantité globale
					if (empty($component['globalqty'])) {
						$newQty = $component['qtyplanned'] * $ratioUpdate;
						// on met la ligne à jour
						$sql = "UPDATE ".MAIN_DB_PREFIX."factorydet ";
						$sql.= " SET qty_planned = ".($newQty?$newQty:'null');
						$sql.= " WHERE rowid = ".$component['rowid'] ;
						$this->db->query($sql);
					}
				}
				return 1;
			} else {
				$this->error=$this->db->error();
				dol_syslog(get_class($this)."::set_description Erreur SQL ".$this->error, LOG_ERR);
				return -1;
			}
		}
	}

	/**
	 *  Return list of contacts emails or mobile existing for third party
	 *
	 *  @param	string	$mode	   		'email' or 'mobile'
	 * 	@param	int		$hidedisabled		1=Hide contact if disabled
	 *  @return array	   				Array of contacts emails or mobile
	*/
	function contact_entrepot_email_array($mode='email', $hidedisabled=0)
	{
		$contact_property = array();
		
		// récupération des contacts société associé à l'entrepot
		$sql = "SELECT s.rowid, s.email, s.statut, s.lastname, s.firstname";
		$sql.= " FROM ".MAIN_DB_PREFIX."socpeople as s";
		$sql.= " ,".MAIN_DB_PREFIX."element_contact as ec";
		$sql.= " ,".MAIN_DB_PREFIX."c_type_contact  as tc";
		$sql.= " WHERE ec.element_id= ".$this->fk_entrepot;
		$sql.= " AND ec.fk_c_type_contact = tc.rowid";
		$sql.= " AND ec.fk_socpeople = s.rowid";
		$sql.= " AND tc.element =  'stock'";
		$sql.= " AND tc.source = 'external'";
		$sql.= " AND tc.active =1";
		$resql=$this->db->query($sql);
		if ($resql) {
			$nump = $this->db->num_rows($resql);
			if ($nump) {
				$i = 0;
				while ($i < $nump) {
					$obj = $this->db->fetch_object($resql);

					// Show all contact. If hidedisabled is 1, showonly contacts with status = 1
					if ($obj->statut == 1 || empty($hidedisabled)) {
						$contact_property["E-".$obj->rowid] = trim(dolGetFirstLastname($obj->firstname, $obj->lastname));
						$contact_property["E-".$obj->rowid].= " &lt;".$obj->email."&gt;";
				 	}
				 	$i++;
				}
			}
		} else
			dol_print_error($this->db);

		// récupération des contacts interne
		$sql = "SELECT s.rowid, s.email, s.statut, s.lastname, s.firstname";
		$sql.= " FROM ".MAIN_DB_PREFIX."user as s";
		$sql.= " ,".MAIN_DB_PREFIX."element_contact as ec";
		$sql.= " ,".MAIN_DB_PREFIX."c_type_contact  as tc";
		$sql.= " WHERE ec.element_id= ".$this->fk_entrepot;
		$sql.= " AND ec.fk_c_type_contact = tc.rowid";
		$sql.= " AND ec.fk_socpeople = s.rowid";
		$sql.= " AND tc.element =  'stock'";
		$sql.= " AND tc.source = 'internal'";
		$sql.= " AND tc.active =1";
		$resql=$this->db->query($sql);
		if ($resql) {
			$nump = $this->db->num_rows($resql);
			if ($nump) {
				$i = 0;
				while ($i < $nump) {
					$obj = $this->db->fetch_object($resql);
				
					// Show all contact. If hidedisabled is 1, showonly contacts with status = 1
					if ($obj->statut == 1 || empty($hidedisabled)) {
						$contact_property["I-".$obj->rowid] = trim(dolGetFirstLastname($obj->firstname, $obj->lastname));
						$contact_property["I-".$obj->rowid].= " &lt;".$obj->email."&gt;";
					}
				 	$i++;
				}
			}
		} else {
			dol_print_error($this->db);
		}
		return $contact_property;
	}

	function createmvtproject($projectid, $productid, $entrepotid, $qtylefted, $idmvt=-1)
	{
		global $user;
		
		$pmp=0;
		$price=0;
		
		// on r�cup�re le pmp et le price pour une utilisation juste des prix
		$sql = "SELECT p.rowid, p.pmp, p.price";
		$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
		$sql.= " WHERE p.rowid=".$productid;

		$resql  = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$pmp=$obj->pmp;
			$price=$obj->price;
		}

		// et on conserve le mouvement
		$sql = "insert into ".MAIN_DB_PREFIX."projet_stock";
		$sql.= " (fk_project, fk_product, fk_entrepot, qty_from_stock, date_creation, fk_user_author,";
		$sql.= " pmp, price, fk_product_stock)";
		$sql.= " values (".$projectid.", ".$productid.", ".$entrepotid.", ".$qtylefted;
		$sql.= ", '".$this->db->idate(dol_now())."'"; // date de cr�ation aliment� automatiquement
		$sql.= ", ".$user->id;
		$sql.= ", ".$pmp.", ".$price.", ".$idmvt.")";
		//print $sql;
		$this->db->query($sql);

	}

	function getProductsListFromOrders()
	{
		$tblof = array();

		// les commandes factorisable sont celle validé non traité (statut à 1)
		// qui n'on pas été associé à un OF
		$sql = 'SELECT cd.rowid, cd.fk_commande, cd.fk_product, cd.qty';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'commande as c, '.MAIN_DB_PREFIX.'commandedet as cd';
		$sql.= ' WHERE c.fk_statut = 1 AND cd.fk_product > 0 AND cd.product_type = 0 AND c.rowid=cd.fk_commande';
		$sql.= ' AND c.rowid not in (SELECT el.fk_source FROM '.MAIN_DB_PREFIX.'element_element as el';
		$sql.= ' WHERE el.sourcetype="commande" AND  el.targettype="factory")';
		$sql.= ' ORDER BY cd.fk_product, c.rowid ';

		$resql=$this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);

			// on constitue la liste des produits à fabriquer
			$i = 0;
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				// si le produit est fabricable
				if ($this->is_FactoryProduct($obj->fk_product) > 0) {
					// on ajoute au tableau ( la ligne de la commande est la clée unique)
					$tblof[] = array ('fk_product' => $obj->fk_product,
												'fk_commande' => $obj->fk_commande,
												'qty' => $obj->qty);
				}
				$i++;
			}
		}
		return $tblof;
	}
}
/**
 *	Class pour simplifier la gestion des extrafields
 */
class Factorydet extends CommonObject 
{
	public $table_element='factorydet';
	public $fields=array(
		'rowid' => array('type'=>'integer', 'label'=>'TechnicalID', 'enabled'=>1, 'position'=>1, 'notnull'=>1, 'visible'=>-1, 'noteditable'=>'1', 'index'=>1, 'comment'=>"Id"),
		'id' => array('type'=>'integer', 'label'=>'TechnicalID', 'enabled'=>1, 'position'=>1, 'notnull'=>1, 'visible'=>-1, 'noteditable'=>'1', 'index'=>1, 'comment'=>"Id"),
		'fk_factory' => array('type'=>'integer:Factory:factory/class/factory.class.php', 'label'=>'factory', 'enabled'=>1, 'position'=>510, 'notnull'=>1, 'visible'=>-2, 'foreignkey'=>'restock.rowid',),
		'fk_product' => array('type'=>'integer', 'label'=>'ProductId', 'enabled'=>1, 'position'=>511, 'notnull'=>-1, 'visible'=>-2,),
		'product_type' => array('type'=>'integer', 'label'=>'ProductType', 'enabled'=>1, 'position'=>511, 'notnull'=>1, 'visible'=>-2,),
		'fk_product_type' => array('type'=>'integer', 'label'=>'ProductType', 'enabled'=>1, 'position'=>511, 'notnull'=>1, 'visible'=>-2,),
		'ref' => array('type'=>'varchar(32)', 'label'=>'Ref', 'enabled'=>1, 'position'=>511, 'notnull'=>-1, 'visible'=>-2,),
		'label' => array('type'=>'text', 'label'=>'Label', 'enabled'=>1, 'position'=>60, 'notnull'=>0, 'visible'=>-3,),
		'pmp' => array('type'=>'integer', 'label'=>'PMPPrice', 'enabled'=>1, 'position'=>511, 'notnull'=>0, 'visible'=>1,),
		'price' => array('type'=>'double', 'label'=>'UnitPrice', 'enabled'=>1, 'position'=>515, 'notnull'=>0, 'visible'=>1,),
		'subprice' => array('type'=>'double', 'label'=>'UnitPrice', 'enabled'=>1, 'position'=>515, 'notnull'=>0, 'visible'=>1,),
		'tva_tx' => array('type'=>'double', 'label'=>'TvaTx', 'enabled'=>1, 'position'=>515, 'notnull'=>0, 'visible'=>1,),
		'qty' => array('type'=>'double', 'label'=>'Quantity', 'enabled'=>1, 'position'=>520, 'notnull'=>0, 'visible'=>1,)
	);

	public $id;
	public $rowid;
	public $qty;
	public $ordercomponent;
	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db	 Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;

	}

	function update($user) {
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'factorydet';
		$sql .= ' SET fk_product = '.$this->fk_product;
		$sql .= ', qty_planned='.$this->qtyplanned;
		//$sql .= ', qty='.$this->qty;
		$sql .= ', pmp='.$this->pmp;
		$sql .= ', price='.$this->price;
		$sql .= ', globalqty='.$this->globalqty;
		$sql .= ', ordercomponent='.$this->ordercomponent;
		$sql .= " WHERE rowid=".$this->rowid; 
		//print $sql;
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		} else {
			return 1;
		}
	}


}

/**
 *	Class pour simplifier la gestion des extrafields
 */
class ProductFactory extends CommonObject 
{
	public $table_element='product_factory';

	public $id;
	public $fk_product_children;
	public $qty;
	public $pmp;
	public $price;
	public $globalqty;
	public $ordercomponent;

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db	 Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;

	}
	function update($user) {
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_factory';
		$sql .= ' SET fk_product_children = '.$this->fk_product;
		$sql .= ', qty = '.$this->qty;
		$sql .= ', pmp = '.$this->pmp;
		$sql .= ', price = '.$this->price;
		$sql .= ', globalqty = '.$this->globalqty;
		$sql .= ', ordercomponent = '.$this->ordercomponent?$this->ordercomponent:'null';
		$sql .= " WHERE rowid = ".$this->id; 
		if (! $this->db->query($sql)) {
			dol_print_error($this->db);
			return -1;
		} else {
			return 1;
		}
	}

}