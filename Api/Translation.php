<?php

namespace Wbengine\Api;

use Wbengine\Application\Env\Http;
use Wbengine\Audit\ChangeLog;
use Wbengine\I18n;
use Wbengine\I18n\ContentTranslations;
use Wbengine\I18n\TranslationException;
use Wbengine\User\Role;

/**
 * Translations of the database content (site titles, menu names, articles), for editors.
 * The default language is the content itself and has no translation.
 */
class Translation extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    /**
     * GET /api/translations/{entity}/{id}: every translation of one item.
     */
    public function getTranslations($entity, $id)
    {
        $this->isAuthenticated(function () use ($entity, $id) {
            $this->answer(function () use ($entity, $id) {
                return array(
                    'success' => true,
                    'entity' => $entity,
                    'id' => (int) $id,
                    'default' => I18n::translator()->getDefaultLocale(),
                    'locales' => I18n::locales(),
                    'translations' => (object) ContentTranslations::all($entity, $id),
                );
            });
        }, Role::EDITOR);
    }

    /**
     * PUT /api/translations/{entity}/{id}/{locale}: store the fields given in the JSON body.
     * Fields that are not given stay as they are; null or "" falls back to the default.
     */
    public function putTranslation($entity, $id, $locale, $body)
    {
        $this->isAuthenticated(function ($payload, $actor) use ($entity, $id, $locale, $body) {
            $this->answer(function () use ($entity, $id, $locale, $body, $actor) {
                if (!is_array($body)) {
                    throw new TranslationException('The body must be a JSON object of fields.');
                }
                $before = $this->currentTranslation($entity, $id, $locale);
                $translation = ContentTranslations::set($entity, $id, $locale, $body);
                if ($before !== $translation) {
                    ChangeLog::recordQuietly('translation', $this->changeId($entity, $id, $locale), 'set', $before, $translation, $this->changeActor($actor), 'v1');
                }
                return array(
                    'success' => true,
                    'translation' => $translation,
                );
            });
        }, Role::EDITOR);
    }

    /**
     * DELETE /api/translations/{entity}/{id}/{locale}: show the item in the default language again.
     */
    public function deleteTranslation($entity, $id, $locale)
    {
        $this->isAuthenticated(function ($payload, $actor) use ($entity, $id, $locale) {
            $this->answer(function () use ($entity, $id, $locale, $actor) {
                $before = $this->currentTranslation($entity, $id, $locale);
                $deleted = ContentTranslations::delete($entity, $id, $locale);
                if ($deleted) {
                    ChangeLog::recordQuietly('translation', $this->changeId($entity, $id, $locale), 'delete', $before, null, $this->changeActor($actor), 'v1');
                }
                return array('success' => true, 'deleted' => $deleted);
            });
        }, Role::EDITOR);
    }

    /** The stored translation before a write, or null. Invalid input is left to the write to refuse. */
    private function currentTranslation($entity, $id, $locale)
    {
        try {
            $all = ContentTranslations::all($entity, $id);
            $match = I18n\LocaleResolver::match($locale, I18n::locales());
            $key = null === $match ? null : I18n\Translator::normalize($match);
            return null !== $key && isset($all[$key]) ? $all[$key] : null;
        } catch (TranslationException $e) {
            return null;
        }
    }

    /** Resource id of a translation in the change log, for example "article:12:cs". */
    private function changeId($entity, $id, $locale)
    {
        return substr($entity . ':' . (int) $id . ':' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $locale), 0, 64);
    }

    /**
     * Send the result of $work as JSON; a TranslationException becomes its 400 or 404.
     */
    private function answer(callable $work)
    {
        try {
            $result = $work();
        } catch (TranslationException $e) {
            $this->Api()->toJson(array('success' => false, 'message' => $e->getMessage()), $e->getCode() ?: Http::BAD_REQUEST);
            return;
        }
        $this->Api()->toJson($result);
    }
}
