<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Yann\Component\IdentityManager\Site\Provider;
\defined('_JEXEC') or die;
use Hybridauth\Storage\StorageInterface;
use Joomla\CMS\Factory;

/** Isolate transient provider tokens in the current Joomla session. */
final class JoomlaStorage implements StorageInterface
{
    public function __construct(private string $alias) {}
    private function data(): array
    {
        return (array) Factory::getApplication()->getSession()->get('identitymanager.hybridauth.' . $this->alias, []);
    }
    public function get($key) { return $this->data()[strtolower($key)] ?? null; }
    public function set($key, $value)
    {
        $data = $this->data();
        $data[strtolower($key)] = $value;
        Factory::getApplication()->getSession()->set('identitymanager.hybridauth.' . $this->alias, $data);
    }
    public function clear() { Factory::getApplication()->getSession()->clear('identitymanager.hybridauth.' . $this->alias); }
    public function delete($key)
    {
        $data = $this->data(); unset($data[strtolower($key)]);
        Factory::getApplication()->getSession()->set('identitymanager.hybridauth.' . $this->alias, $data);
    }
    public function deleteMatch($key)
    {
        foreach (array_keys($this->data()) as $stored) {
            if (str_contains($stored, strtolower($key))) { $this->delete($stored); }
        }
    }
}
