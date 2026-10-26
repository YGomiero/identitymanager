<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Yann\Component\IdentityManager\Administrator\Controller;
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/** Administrator-only archive and purge actions; both require a POST CSRF token. */
final class LogsController extends BaseController
{
    private function authorizeAction(): void
    {
        if ($this->input->getMethod() !== 'POST' || !$this->checkToken('post', false)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_identitymanager')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    public function clear(): void
    {
        $this->authorizeAction();
        $db = Factory::getContainer()->get('DatabaseDriver');
        // DELETE preserves the sequence and does not reset concurrent log identifiers.
        $db->setQuery($db->createQuery()->delete('#__identitymanager_logs'))->execute();
        $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=logs', false), Text::_('COM_IDENTITYMANAGER_LOGS_CLEARED'));
    }

    public function export(): void
    {
        $this->authorizeAction();
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $maximum = (int) $db->setQuery('SELECT MAX(id) FROM #__identitymanager_logs')->loadResult();
        $app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="identitymanager-logs-' . gmdate('Ymd-His') . '.csv"', true);
        $app->setHeader('Cache-Control', 'no-store', true);
        $app->sendHeaders();
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        $columns = ['id','created_at','event_type','status','user_id','provider_id','provider_name','message','ip_address','user_agent'];
        fputcsv($output, $columns, ';', '"', '');
        $last = 0;
        do {
            $query = $db->createQuery()->select($db->quoteName($columns))->from('#__identitymanager_logs')->where('id > ' . $last)->where('id <= ' . $maximum)->order('id ASC');
            $rows = $db->setQuery($query, 0, 500)->loadAssocList();
            foreach ($rows as $row) {
                $last = (int) $row['id'];
                $row['message'] = Text::_((string) $row['message']);
                fputcsv($output, array_map([self::class, 'safeCell'], array_values($row)), ';', '"', '');
            }
        } while (count($rows) === 500);
        fclose($output);
        $app->close();
    }

    /** Prevent formula execution when untrusted profile data is opened in a spreadsheet. */
    public static function safeCell($value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[\s]*[=+@-]/u', $value) || preg_match('/^[\t\r\n]/', $value) ? "'" . $value : $value;
    }
}
