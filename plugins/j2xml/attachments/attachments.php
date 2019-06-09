<?php
/**
 * @package		J2XML
 * @subpackage	plg_j2xml_attachments
 * 
 * @version		3.7
 * @since		3.0
 *
 * @author		Helios Ciancio <info@eshiol.it>
 * @link		http://www.eshiol.it
 * @copyright	Copyright (C) 2015 - 2019 Helios Ciancio. All Rights Reserved
 * @license		http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL v3
 * J2XML is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License 
 * or other free or open source software licenses.
 */
 
// no direct access
defined('_JEXEC') or die('Restricted access.');

use eshiol\J2XML\Table\Attachment;
use eshiol\J2XML\Version;
use Joomla\Registry\Registry;

jimport('joomla.plugin.plugin');
jimport('joomla.application.component.helper');
jimport('joomla.filesystem.file');

\JLoader::import('eshiol.J2xml.Table.Attachment', __DIR__);
\JLoader::import('eshiol.J2xml.Version');

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

		if ($this->params->get('debug') || defined('JDEBUG') && JDEBUG)
		{
			JLog::addLogger(array('text_file' => $this->params->get('log', 'eshiol.log.php'), 'extension' => 'plg_j2xml_attachments_file'), JLog::ALL, array('plg_j2xml_attachments'));
		}

		if (PHP_SAPI == 'cli')
		{
			JLog::addLogger(array('logger' => 'echo', 'extension' => 'plg_j2xml_attachments'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_attachments'));
		}
		else
		{
			JLog::addLogger(array('logger' => $this->params->get('logger', 'messagequeue'), 'extension' => 'plg_j2xml_attachments'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_attachments'));
			if ($this->params->get('phpconsole') && class_exists('JLogLoggerPhpconsole'))
			{
				JLog::addLogger(array('logger' => 'phpconsole', 'extension' => 'plg_j2xml_attachments_phpconsole'),  JLOG::DEBUG, array('plg_j2xml_attachments'));
			}
		}

		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'plg_j2xml_attachments'));
		
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
		{
			JLog::addLogger(array('logger' => 'echo', 'extension' => 'plg_j2xml_attachments'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_attachments'));
		}
		else
		{
			JLog::addLogger(array('logger' => $options->get('logger', 'messagequeue'), 'extension' => 'plg_j2xml_attachments'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_attachments'));
		}

		if (version_compare(Version::getFullVersion(), '19.4.330') == -1)
		{
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_LIB'), JLOG::WARNING, 'plg_j2xml_attachments'));
			return false;
		}
		
		// Ignore warnings because component may not be installed
		$warnHandlers = JERROR::getErrorHandling( E_WARNING );
		JERROR::setErrorHandling( E_WARNING, 'ignore' );
		
		// Check if component is installed
		if ( !JComponentHelper::isEnabled( 'com_attachments', true) ) {
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_COM'), JLOG::WARNING, 'plg_j2xml_attachments'));
			return false;
		}

		// Reset the warning handler(s)
		foreach( $warnHandlers as $mode ) {
			JERROR::setErrorHandling( E_WARNING, $mode );
		}

		if ($ids = implode(',', $xml->xpath("/j2xml/content/id")))
		{
			$db    = \JFactory::getDbo();
			$query = $db->getQuery(true);
			$query
				->select('id')
				->from('#__attachments')
				->where('parent_type = "com_content"')
				->where('parent_entity = "article"')
				->where('parent_id IN ('.$ids.')')
				;
			$db->setQuery($query);
			
			$options['max_attachment_size'] = (int)$this->_params->get('max_attachment_size', 0);
			
			$ids_attachment = $db->loadColumn();
			if ($ids_attachment)
			{
				foreach ($ids_attachment as $id)
				{
					Attachment::export($id, $xml, $options);
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
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'plg_j2xml_attachments'));
		JLog::add(new JLogEntry($context, JLOG::DEBUG, 'plg_j2xml_attachments'));
		JLog::add(new JLogEntry(print_r($options, true), JLOG::DEBUG, 'plg_j2xml_attachments'));

		if (PHP_SAPI == 'cli')
		{
			JLog::addLogger(array('logger' => 'echo', 'extension' => 'plg_j2xml_attachments'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_attachments'));
		}
		else
		{
			JLog::addLogger(array('logger' => $options->get('logger', 'messagequeue'), 'extension' => 'plg_j2xml_attachments'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_attachments'));
		}

		if (version_compare(Version::getFullVersion(), '19.4.330') == -1)
		{
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_LIB'), JLOG::WARNING, 'plg_j2xml_attachments'));
			return false;
		}
		
		// Check if component is not installed
		if (!file_exists(JPATH_ADMINISTRATOR.'/components/com_attachments/attachments.php'))
		{
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_COM'), JLOG::WARNING, 'plg_j2xml_attachments'));
			return false;
		}
		// Check if component is not enabled
		if (!JComponentHelper::isEnabled('com_attachments', true)) 
		{
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_ATTACHMENTS').' '.JText::_('PLG_J2XML_ATTACHMENTS_MSG_REQUIREMENTS_COM'), JLOG::WARNING, 'plg_j2xml_attachments'));
			return false;
		}

		Attachment::import($xml, $options);

		return true;
	}
}
