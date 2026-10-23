<?php

namespace Wbengine\Api;

use Wbengine\Application\Env\Http;
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
        $this->isAuthenticated(function () use ($entity, $id, $locale, $body) {
            $this->answer(function () use ($entity, $id, $locale, $body) {
                if (!is_array($body)) {
                    throw new TranslationException('The body must be a JSON object of fields.');
                }
                return array(
                    'success' => true,
                    'translation' => ContentTranslations::set($entity, $id, $locale, $body),
                );
            });
        }, Role::EDITOR);
    }

    /**
     * DELETE /api/translations/{entity}/{id}/{locale}: show the item in the default language again.
     */
    public function deleteTranslation($entity, $id, $locale)
    {
        $this->isAuthenticated(function () use ($entity, $id, $locale) {
            $this->answer(function () use ($entity, $id, $locale) {
                return array('success' => true, 'deleted' => ContentTranslations::delete($entity, $id, $locale));
            });
        }, Role::EDITOR);
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
