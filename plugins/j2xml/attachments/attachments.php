<?php
/**
 * @version		3.0.5 plugins/j2xml/attachments/attachments.php
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

jimport('joomla.plugin.plugin');
jimport('joomla.application.component.helper');
jimport('joomla.filesystem.file');
jimport('eshiol.j2xml.version');

class PlgJ2xmlAttachments extends JPlugin
{
	var $_params = null;
	/**
	 * CONSTRUCTOR
	 * @param object $subject The object to observe
	 * @param object $params  The object that holds the plugin parameters
	 * @since 1.5
	 */
	function __construct(&$subject, $params)
	{
		parent::__construct($subject, $params);		

		JLog::addLogger(array('text_file' => 'j2xml.php', 'extension' => 'plg_j2xml_attachments'), JLog::ALL, array('plg_j2xml_attachments'));		
		JLog::add(new JLogEntry(__METHOD__,JLOG::DEBUG,'plg_j2xml_attachments'));
		
		$lang = JFactory::getLanguage();
		$lang->load('plg_j2xml_attachments', JPATH_SITE, null, false, false)
			|| $lang->load('plg_j2xml_attachments', JPATH_ADMINISTRATOR, null, false, false)
			|| $lang->load('plg_j2xml_attachments', JPATH_SITE, null, true)
			|| $lang->load('plg_j2xml_attachments', JPATH_ADMINISTRATOR, null, true);	
	}

	/**
	 * Method is called by 
	 *
	 * @access	public
	 */
	public function onAfterExport($context, &$xml, $options)
	{
		JLog::add(new JLogEntry(__METHOD__,JLOG::DEBUG,'plg_j2xml_attachments'));
		JLog::add(new JLogEntry($context,JLOG::DEBUG,'plg_j2xml_attachments'));

		if (PHP_SAPI == 'cli')
			JLog::addLogger(array('logger' => 'echo', 'extension' => 'plg_j2xml_attachments'), JLOG::INFO, array('plg_j2xml_attachments'));
		else
			JLog::addLogger(array('logger' => $options->get('logger', 'messagequeue'), 'extension' => 'plg_j2xml_attachments'), JLOG::INFO, array('plg_j2xml_attachments'));
		
		if (version_compare(J2XMLVersion::getShortVersion(), '15.8.4') == -1)
		{
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_LIB'),JLOG::WARNING,'plg_j2xml_attachments'));
			return false;
		}
		
		// Ignore warnings because component may not be installed
		$warnHandlers = JERROR::getErrorHandling( E_WARNING );
		JERROR::setErrorHandling( E_WARNING, 'ignore' );
		
		// Check if component is installed
		if ( !JComponentHelper::isEnabled( 'com_attachments', true) ) {
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_COM'),JLOG::WARNING,'plg_j2xml_attachments'));
			return false;
		}
		
		// Reset the warning handler(s)
		foreach( $warnHandlers as $mode ) {
			JERROR::setErrorHandling( E_WARNING, $mode );
		}
		
		if ($ids = implode(',', $xml->xpath("/j2xml/content/id")))
		{
			$db      = JFactory::getDbo();
			$doc = dom_import_simplexml($xml)->ownerDocument;
			$fragment = $doc->createDocumentFragment();
			require_once dirname(__FILE__).'/attachment.php';
			
			$query   = $db->getQuery(true);
			$query
				->select('id')
				->from('#__attachments')
				->where('parent_type = "com_content"')
				->where('parent_entity = "article"')
				->where('parent_id IN ('.$ids.')')
				;
			$db->setQuery($query);
			$ids_attachment = $db->loadColumn();
			if ($ids_attachment)
			{
				foreach ($ids_attachment as $id)
				{
					$item = JTable::getInstance('attachment', 'eshTable');
					$item->load($id);
					$fragment->appendXML($item->toXML());
					$doc->documentElement->appendChild($fragment);
				}
			}
		}
		return true;
	}
	
	/**
	 * Method is called by
	 *
	 * @access	public
	 */
	public function onAfterImport($context, &$xml, $options)
	{
		JLog::add(new JLogEntry(__METHOD__,JLOG::DEBUG,'plg_j2xml_attachments'));
		JLog::add(new JLogEntry($context,JLOG::DEBUG,'plg_j2xml_attachments'));
	
		if (PHP_SAPI == 'cli')
			JLog::addLogger(array('logger' => 'echo', 'extension' => 'plg_j2xml_attachments'), JLOG::INFO, array('plg_j2xml_attachments'));
		else
			JLog::addLogger(array('logger' => $options->get('logger', 'messagequeue'), 'extension' => 'plg_j2xml_attachments'), JLOG::INFO, array('plg_j2xml_attachments'));
		
		if (version_compare(J2XMLVersion::getShortVersion(), '15.8.4') == -1)
		{
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_LIB'),JLOG::WARNING,'plg_j2xml_attachments'));
			return false;
		}
		
		// Ignore warnings because component may not be installed
		$warnHandlers = JERROR::getErrorHandling( E_WARNING );
		JERROR::setErrorHandling( E_WARNING, 'ignore' );
		
		// Check if component is installed
		if ( !JComponentHelper::isEnabled( 'com_attachments', true) ) {
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_COM'),JLOG::WARNING,'plg_j2xml_attachments'));
			return false;
		}
		
		// Reset the warning handler(s)
		foreach( $warnHandlers as $mode ) {
			JERROR::setErrorHandling( E_WARNING, $mode );
		}
		
		jimport('eshiol.j2xml.importer');
		
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		foreach ($xml->xpath("/j2xml/attachment[parent_type = 'com_content' and parent_entity = 'article']") as $record)
		{
			$data = array();
			foreach($record->children() as $key => $value)
				$data[trim($key)] = trim($value);
			$attachment = new stdClass();
			$attachment->filename = html_entity_decode($data['filename']);
			$attachment->file_type = $data['file_type'];
			$attachment->file_size = $data['file_size'];
			$attachment->parent_type = $data['parent_type'];
			$attachment->parent_entity = $data['parent_entity'];
			$attachment->parent_id = J2XMLImporter::getArticledId($data['parent_id']);
			$attachment->uri_type = $data['uri_type'];
			if ($data['uri_type'] == 'url')
			{
				$attachment->url = $data['url']; 
				$attachment->filename_sys = '';
			}
			else
			{
				require_once(JPATH_SITE.'/components/com_attachments/helper.php');
				
				// Get the component parameters
				jimport('joomla.application.component.helper');
				$params = JComponentHelper::getParams('com_attachments');
				
				// Define where the attachments go
				$upload_url = AttachmentsDefines::$ATTACHMENTS_SUBDIR;
				$upload_dir = JPATH_SITE.'/'.$upload_url;
				
				// Get the parent plugin manager
				JPluginHelper::importPlugin('attachments');
				$apm = getAttachmentsPluginManager();
				
				// Get the parent object
				$parent = $apm->getAttachmentsPlugin($attachment->parent_type);

				// Construct the system filename and url (based on entities, etc)
				$newdir = $parent->getAttachmentPath($attachment->parent_entity, $attachment->parent_id, null);
				$fullpath = $upload_dir.'/'.$newdir;

				// Make sure the directory exists
				if ( !JFile::exists($fullpath) ) {
					jimport( 'joomla.filesystem.folder' );
					if ( !JFolder::create($fullpath) ) {
						$errmsg = JText::sprintf('ATTACH_ERROR_UNABLE_TO_SETUP_UPLOAD_DIR_S', $upload_dir) . ' (ERR 34)';
						JError::raiseError(500, $errmsg);
					}
					require_once(JPATH_SITE.'/components/com_attachments/helper.php');
					AttachmentsHelper::write_empty_index_html($fullpath);
				}
				$attachment->filename_sys = $fullpath.$attachment->filename;				
				file_put_contents($attachment->filename_sys, base64_decode($data['file']));	
				$attachment->filename_sys = utf8_encode($attachment->filename_sys);
				$attachment->filename = utf8_encode($attachment->filename);
				$attachment->url = $upload_url.'/'.$newdir.$attachment->filename;	
			}
			$attachment->url_valid = $data['url_valid'];
			$attachment->url_relative = $data['url_relative'];
			$attachment->url_verify = $data['url_verify'];
			$attachment->display_name = $data['display_name'];
			$attachment->description = $data['description'];
			$attachment->icon_filename = $data['icon_filename'];				
			$attachment->access = J2XMLImporter::getAccessId($data['access']);
			$attachment->state = $data['state'];
			$attachment->user_field_1 = $data['user_field_1'];
			$attachment->user_field_2 = $data['user_field_2'];
			$attachment->user_field_3 = $data['user_field_3'];
			$attachment->created = $data['created'];
			$attachment->created_by = J2XMLImporter::getUserId($data['created_by']);
			$attachment->modified = $data['modified'];
			$attachment->modified_by = J2XMLImporter::getUserId($data['modified_by']);
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
				$attachments->id = $id;
				if ($db->updateObject('#__attachments', $attachments, 'id'))
					JLog::add(new JLogEntry(JText::sprintf('PLG_J2XML_ATTACHMENTS_MSG_ATTACHMENT_IMPORTED', $attachment->filename), JLOG::INFO, 'plg_j2xml_attachments'));
				else 
					JLog::add(new JLogEntry(JText::sprintf('PLG_J2XML_ATTACHMENTS_MSG_ATTACHMENT_NOT_IMPORTED', $attachment->filename), JLOG::INFO, 'plg_j2xml_attachments'));
			}
			else
			{
				if ($db->insertObject('#__attachments', $attachment))
					JLog::add(new JLogEntry(JText::sprintf('PLG_J2XML_ATTACHMENTS_MSG_ATTACHMENT_IMPORTED', $attachment->filename), JLOG::INFO, 'plg_j2xml_attachments'));
				else
					JLog::add(new JLogEntry(JText::sprintf('PLG_J2XML_ATTACHMENTS_MSG_ATTACHMENT_NOT_IMPORTED', $attachment->filename), JLOG::INFO, 'plg_j2xml_attachments'));
			}
		}
		return true;
	}	
}
