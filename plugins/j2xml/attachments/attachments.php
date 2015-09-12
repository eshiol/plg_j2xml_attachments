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

jimport('joomla.plugin.plugin');
jimport('joomla.application.component.helper');
jimport('joomla.filesystem.file');
jimport('eshiol.j2xml.version');

use Joomla\Registry\Registry;

class PlgJ2xmlAttachments extends JPlugin
{ 
	protected $_params = null;
	protected $_user_id;
	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);
		JLog::addLogger(array('text_file' => 'j2xml.php', 'extension' => 'plg_j2xml_attachments'), JLog::ALL, array('plg_j2xml_attachments'));		
		JLog::add(new JLogEntry(__METHOD__,JLOG::DEBUG,'plg_j2xml_attachments'));
		// Get the parameters.
		if (isset($config['params']))
		{
			if ($config['params'] instanceof Registry)
			{
				$this->_params = $config['params'];
			}
			else
			{
				$this->_params = (version_compare(JPlatform::RELEASE, '12', 'ge') ? new Registry : new JRegistry);
				$this->_params->loadString($config['params']);
			}
		}
		
		$user = JFactory::getUser();
		$this->_user_id = $user->get('id');
		
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
		JLog::add(new JLogEntry(print_r($this->_params, true),JLOG::DEBUG,'plg_j2xml_attachments'));
		
		if (PHP_SAPI == 'cli')
			JLog::addLogger(array('logger' => 'echo', 'extension' => 'plg_j2xml_attachments'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_attachments'));
		else
			JLog::addLogger(array('logger' => $options->get('logger', 'messagequeue'), 'extension' => 'plg_j2xml_attachments'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_attachments'));
				
		if (version_compare(J2XMLVersion::getShortVersion(), '15.9.5') == -1)
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
					JLog::add(new JLogEntry(print_r($item, true),JLOG::DEBUG,'plg_j2xml_attachments'));
					$attachment = $item->toXML();
					if ($item->uri_type == 'file')
					{
						// Check the file size
						$max_attachment_size = (int)$this->_params->get('max_attachment_size', 0);
						if ($max_attachment_size > 0) 
						{
							$file_size_kb = filesize($item->filename_sys) / 1024;
							if ($file_size_kb > $max_attachment_size)
								$attachment = preg_replace('/<file>.+?<\/file>/im', '', $attachment);
						}
					}
					$fragment->appendXML($attachment);
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
			JLog::addLogger(array('logger' => 'echo', 'extension' => 'plg_j2xml_attachments'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_attachments'));
		else
			JLog::addLogger(array('logger' => $options->get('logger', 'messagequeue'), 'extension' => 'plg_j2xml_attachments'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_attachments'));
		
		if (version_compare(J2XMLVersion::getShortVersion(), '15.9.5') == -1)
		{
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_LIB'),JLOG::WARNING,'plg_j2xml_attachments'));
			return false;
		}
		
		// Check if component is not installed
		if (!file_exists(JPATH_ADMINISTRATOR.'/components/com_attachments/attachments.php'))
		{
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_COM'),JLOG::WARNING,'plg_j2xml_attachments'));
			return false;
		}
		// Check if component is not enabled
		if (!JComponentHelper::isEnabled('com_attachments', true)) 
		{
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_COM'),JLOG::WARNING,'plg_j2xml_attachments'));
			return false;
		}
		
		jimport('eshiol.j2xml.importer');
		
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		foreach ($xml->xpath("/j2xml/attachment[parent_type = 'com_content' and parent_entity = 'article']") as $record)
		{
			$importer = new J2XMLImporter();
			$importer->prepareData($record, $data, $options);

			$attachment = new stdClass();
			$attachment->filename = html_entity_decode($data['filename']);
			$attachment->file_type = $data['file_type'];
			$attachment->file_size = $data['file_size'];
			$attachment->parent_type = $data['parent_type'];
			$attachment->parent_entity = $data['parent_entity'];
			$attachment->parent_id = $importer->getArticledId($data['parent_id']);
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
			else
			{
				JLog::add(new JLogEntry(JText::sprintf('PLG_J2XML_ATTACHMENTS_MSG_ATTACHMENT_NOT_EXPORTED', $attachment->filename), JLOG::WARNING, 'plg_j2xml_attachments'));
				return;
			}
			$attachment->url_valid = $data['url_valid'];
			$attachment->url_relative = $data['url_relative'];
			$attachment->url_verify = $data['url_verify'];
			$attachment->display_name = $data['display_name'];
			$attachment->description = $data['description'];
			$attachment->icon_filename = $data['icon_filename'];				
			$attachment->access = $importer->getAccessId($data['access']);
			$attachment->state = $data['state'];
			$attachment->user_field_1 = $data['user_field_1'];
			$attachment->user_field_2 = $data['user_field_2'];
			$attachment->user_field_3 = $data['user_field_3'];
			$attachment->created = $data['created'];
			$attachment->created_by = $importer->getUserId($data['created_by'], $this->_user_id);
			$attachment->modified = $data['modified'];
			$attachment->modified_by = $importer->getUserId($data['modified_by'], 0);
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
