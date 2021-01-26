<?php
/**
 * @package     Joomla.Plugins
 * @subpackage  J2xml.Attachments
 *
 * @version     __DEPLOY_VERSION__
 * @since		3.0
 *
 * @author      Helios Ciancio <info (at) eshiol (dot) it>
 * @link        https://www.eshiol.it
 * @copyright   Copyright (C) 2015 - 2021 Helios Ciancio. All Rights Reserved
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL v3
 * J2XML is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License
 * or other free or open source software licenses.
 */

namespace eshiol\J2XML\Table;
// no direct access
defined('_JEXEC') or die('Restricted access.');

use eshiol\J2XML\Table\Table;

\JLoader::import('eshiol.J2xml.Table.Table');

/**
 *
 * Attachment Table
 *
 */
class Attachment extends Table
{
	/**
	 * Constructor
	 *
	 * @param \JDatabaseDriver $db
	 *        	A database connector object
	 */
	public function __construct (\JDatabaseDriver $db)
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'com_j2xml'));
		
		parent::__construct('#__attachments', 'id', $db);
	}

	/**
	 * Export item list to xml
	 *
	 * @access public
	 */
	function toXML ($mapKeysToText = false)
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'com_j2xml'));

		$this->_excluded = array_merge($this->_excluded, array('filename_sys'));
		$this->_aliases['parent_id']='SELECT CONCAT(cc.path,\'/\',c.alias) FROM #__content c LEFT JOIN #__categories cc ON c.catid = cc.id WHERE c.id = '.(int)$this->parent_id;
		if ($this->uri_type == 'file')
		{
			$this->_excluded = array_merge($this->_excluded, array('url'));
			$this->_aliases['file'] = 'SELECT \''.base64_encode(file_get_contents($this->filename_sys)).'\' FROM DUAL';
		}

		return $this->_serialize();
	}

	/**
	 * Export data
	 *
	 * @param int $id
	 *        	the id of the item to be exported
	 * @param \SimpleXMLElement $xml
	 *        	xml
	 * @param array $options
	 *
	 * @throws
	 * @return void
	 * @access public
	 *
	 * @since 3.7
	 */
	public static function export ($id, &$xml, $options)
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'plg_j2xml_attachments'));
		\JLog::add(new \JLogEntry('id: ' . $id, \JLog::DEBUG, 'plg_j2xml_attachments'));
		\JLog::add(new \JLogEntry('options: ' . print_r($options, true), \JLog::DEBUG, 'plg_j2xml_attachments'));
		
		if ($xml->xpath("//j2xml/attachment/id[text() = '" . $id . "']"))
		{
			return;
		}
		
		$db = \JFactory::getDbo();
		$item = new Attachment($db);
		if (! $item->load($id))
		{
			return;
		}

		$db = \JFactory::getDbo();
		$item = new Attachment($db);
		if (! $item->load($id))
		{
			return;
		}

		$doc = dom_import_simplexml($xml)->ownerDocument;
		$fragment = $doc->createDocumentFragment();

		$attachment = $item->toXML();

		if ($item->uri_type == 'file')
		{
			// Check the file size
			if ($options['max_attachment_size'] > 0)
			{
				$file_size_kb = filesize($item->filename_sys) / 1024;
				if ($file_size_kb > $options['max_attachment_size'])
				{
					$attachment = preg_replace('/<file>.+?<\/file>/im', '', $attachment);
				}
			}
		}

		$fragment->appendXML($attachment);
		$doc->documentElement->appendChild($fragment);
	}

	/**
	 * Import data
	 *
	 * @param \SimpleXMLElement $xml
	 *        	xml
	 * @param \JRegistry $params
	 *
	 * @throws
	 * @return void
	 * @access public
	 *
	 * @since 18.8.310
	 */
	public static function import ($xml, &$params)
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'plg_j2xml_attachments'));
		
		$app = \JFactory::getApplication();
		$db  = \JFactory::getDBO();
		foreach ($xml->xpath("/j2xml/attachment[parent_type = 'com_content' and parent_entity = 'article']") as $record)
		{
			self::prepareData($record, $data, $params);

			$attachment = new \stdClass();
			$attachment->filename = html_entity_decode($data['filename']);
			$attachment->file_type = $data['file_type'];
			$attachment->file_size = $data['file_size'];
			$attachment->parent_type = $data['parent_type'];
			$attachment->parent_entity = $data['parent_entity'];
			$attachment->parent_id = self::getArticleId($data['parent_id']);
			$attachment->uri_type = $data['uri_type'];
			if ($data['uri_type'] == 'url')
			{
				$attachment->url = $data['url'];
				$attachment->filename_sys = '';
			}
			elseif (isset($data['file']))
			{
				require_once(JPATH_SITE.'/components/com_attachments/helper.php');

				// Get the component parameters
				jimport('joomla.application.component.helper');
				$params = \JComponentHelper::getParams('com_attachments');

				// Define where the attachments go
				$upload_url = \AttachmentsDefines::$ATTACHMENTS_SUBDIR;
				$upload_dir = JPATH_SITE.'/'.$upload_url;

				// Get the parent plugin manager
				\JPluginHelper::importPlugin('attachments');
				$apm = getAttachmentsPluginManager();

				// Get the parent object
				$parent = $apm->getAttachmentsPlugin($attachment->parent_type);

				// Construct the system filename and url (based on entities, etc)
				$newdir = $parent->getAttachmentPath($attachment->parent_entity, $attachment->parent_id, null);
				$fullpath = $upload_dir.'/'.$newdir;

				// Make sure the directory exists
				if ( !\JFile::exists($fullpath) ) 
				{
					jimport( 'joomla.filesystem.folder' );
					if ( !\JFolder::create($fullpath) ) 
					{
						$errmsg = \JText::sprintf('ATTACH_ERROR_UNABLE_TO_SETUP_UPLOAD_DIR_S', $upload_dir) . ' (ERR 34)';
						\JError::raiseError(500, $errmsg);
					}
					require_once(JPATH_SITE.'/components/com_attachments/helper.php');
					\AttachmentsHelper::write_empty_index_html($fullpath);
				}
				$attachment->filename_sys = $fullpath.$attachment->filename;
				file_put_contents($attachment->filename_sys, base64_decode($data['file']));
				$attachment->filename_sys = utf8_encode($attachment->filename_sys);
				$attachment->filename = utf8_encode($attachment->filename);
				$attachment->url = $upload_url.'/'.$newdir.$attachment->filename;
			}
			else
			{
				\JLog::add(new \JLogEntry(\JText::sprintf('PLG_J2XML_ATTACHMENTS_MSG_ATTACHMENT_NOT_EXPORTED', $attachment->filename), \JLog::WARNING, 'plg_j2xml_attachments'));
				return;
			}
			$attachment->url_valid = $data['url_valid'];
			$attachment->url_relative = $data['url_relative'];
			$attachment->url_verify = $data['url_verify'];
			$attachment->display_name = $data['display_name'];
			$attachment->description = $data['description'];
			$attachment->icon_filename = $data['icon_filename'];
			$attachment->access = self::getAccessId($data['access']);
			$attachment->state = $data['state'];
			$attachment->user_field_1 = $data['user_field_1'];
			$attachment->user_field_2 = $data['user_field_2'];
			$attachment->user_field_3 = $data['user_field_3'];
			$attachment->created = $data['created'];
			$attachment->created_by = self::getUserId($data['created_by']);
			$attachment->modified = $data['modified'];
			$attachment->modified_by = self::getUserId($data['modified_by'], 0);
			$attachment->download_count = $data['download_count'];
			$query = $db->getQuery(true);
			$query->select($db->quoteName('id'));
			$query->from($db->quoteName('#__attachments'));
			$query->where($db->quoteName('parent_type').'='.$db->quote($attachment->parent_type));
			$query->where($db->quoteName('parent_entity').'='.$db->quote($attachment->parent_entity));
			$query->where($db->quoteName('filename').'='.$db->quote($attachment->filename));
			$query->where($db->quoteName('uri_type').'='.$db->quote($attachment->uri_type));
			$db->setQuery($query);
			$id = $db->loadResult();
			if ($id)
			{
				$attachment->id = $id;
				if ($db->updateObject('#__attachments', $attachment, 'id'))
				{
					\JLog::add(new \JLogEntry(\JText::sprintf('PLG_J2XML_ATTACHMENTS_MSG_ATTACHMENT_IMPORTED', $attachment->filename), \JLog::INFO, 'plg_j2xml_attachments'));
				}
				else
				{
					\JLog::add(new \JLogEntry(\JText::sprintf('PLG_J2XML_ATTACHMENTS_MSG_ATTACHMENT_NOT_IMPORTED', $attachment->filename), \JLog::INFO, 'plg_j2xml_attachments'));
				}
			}
			else
			{
				if ($db->insertObject('#__attachments', $attachment))
				{
					\JLog::add(new \JLogEntry(\JText::sprintf('PLG_J2XML_ATTACHMENTS_MSG_ATTACHMENT_IMPORTED', $attachment->filename), \JLog::INFO, 'plg_j2xml_attachments'));
				}
				else
				{
					\JLog::add(new \JLogEntry(\JText::sprintf('PLG_J2XML_ATTACHMENTS_MSG_ATTACHMENT_NOT_IMPORTED', $attachment->filename), \JLog::INFO, 'plg_j2xml_attachments'));
				}
			}
		}
	}
}
