<?php
/**
 * @version		3.0.6 plugins/j2xml/attachments/attachments.php
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
		$this->_excluded = array_merge($this->_excluded, array('filename_sys'));
		$this->_aliases['parent_id']='SELECT CONCAT(cc.path,\'/\',c.alias) FROM #__content c LEFT JOIN #__categories cc ON c.catid = cc.id WHERE c.id = '.(int)$this->parent_id;
		if ($this->uri_type == 'file')
		{
			$this->_excluded = array_merge($this->_excluded, array('url'));
			$this->_aliases['file'] = 'SELECT \''.base64_encode(file_get_contents($this->filename_sys)).'\' FROM DUAL';
		}		
		return parent::_serialize();
	}
}
