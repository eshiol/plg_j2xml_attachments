<?php
/**
 * @version		3.0.1 plugins/j2xml/attachments/attachments.php
 * 
 * @package		J2XML
 * @subpackage	plg_j2xml_attachments
 * @since		3.0
 *
 * @author		Helios Ciancio <info@eshiol.it>
 * @link		http://www.eshiol.it
 * @copyright	Copyright (C) 2015 Helios Ciancio. All Rights Reserved
 * @license		http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL v3
 * J2XML is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License 
 * or other free or open source software licenses.
 */
 
// no direct access
defined('_JEXEC') or die('Restricted access.');

/**
* Attachment Table class
*/
class eshTableAttachment extends eshTable
{
	/**
	 * Constructor
	 *
	 * @param object Database connector object
	 */
	function __construct(& $db) {
		parent::__construct('#__attachments', 'id', $db);
	}

	/**
	 * Export item list to xml
	 *
	 * @access public
	 */
	function toXML($mapKeysToText = false)
	{
		$xml = ''; 
		
		// Initialise variables.
		$xml = array();
		
		// Open root node.
		$xml[] = '<attachment>';
		
		$excluded = array('filename_sys'); 
		$aliases = array(
			'access'=>'SELECT IF(f.id<=6,f.id,f.title) FROM #__viewlevels f RIGHT JOIN #__attachments a ON f.id = a.access WHERE a.id = '. (int)$this->id,
			'parent_id'=>'SELECT CONCAT(cc.path,\'/\',c.alias) FROM #__content c LEFT JOIN #__categories cc ON c.catid = cc.id WHERE c.id = '.(int)$this->parent_id,
			'created_by'=>'SELECT username FROM #__users WHERE id = '.(int)$this->created_by,
			'modified_by'=>'SELECT username modified_by FROM #__users WHERE id = '.(int)$this->modified_by
			);
		
		if ($this->uri_type == 'file')
		{
			$excluded[] = 'url';
			$aliases['file'] = 'SELECT \''.base64_encode(file_get_contents($this->filename_sys)).'\' FROM DUAL'; 
		}
		
		$xml[] = parent::_serialize(
			$excluded, 
			$aliases,
			array()
		); // $excluded,$aliases,$jsons

		// Close root node.
		$xml[] = '</attachment>';
						
		// Return the XML array imploded over new lines.
		return implode("\n", $xml);
	}
}
