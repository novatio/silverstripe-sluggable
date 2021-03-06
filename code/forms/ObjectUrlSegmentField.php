<?php

/**
 * Used to edit the SiteTree->URLSegment property, and suggest input based on the serverside rules
 * defined through {@link SiteTree->generateURLSegment()} and {@link URLSegmentFilter}.
 *
 * Note: The actual conversion for saving the value takes place in the model layer.
 *
 * @package    cms
 * @subpackage forms
 */
class ObjectURLSegmentField extends TextField
{
    /**
     * @var string
     */
    protected $helpText, $urlPrefix, $urlSuffix;

    private static $allowed_actions = [
        'suggest',
    ];

    public function Value()
    {
        return rawurldecode($this->value);
    }

    public function getAttributes()
    {
        return array_merge(
            parent::getAttributes(),
            [
                'data-prefix' => $this->getURLPrefix(),
                'data-suffix' => '?preview',
            ]
        );
    }

    public function Field($properties = [])
    {
        Requirements::javascript(CMS_DIR . '/javascript/CMSMain.EditForm.js');
        Requirements::javascript(CMS_DIR . '/javascript/SiteTreeURLSegmentField.js');
        Requirements::add_i18n_javascript(CMS_DIR . '/javascript/lang', false, true);
        Requirements::css(CMS_DIR . "/css/screen.css");

        return parent::Field($properties);
    }

    public function suggest($request)
    {
        if (!$request->getVar('value')) {
            return $this->httpError(405,
                _t('SiteTreeURLSegmentField.EMPTY', 'Please enter a URL Segment or click cancel')
            );
        }
        $object = $this->getObject();

        // Same logic as SiteTree->onBeforeWrite
        $object->URLSegment = $object->generateURLSegment($request->getVar('value'));
        $count = 1;
        while (!$object->validURLSegment()) {
            $object->URLSegment = preg_replace('/-[0-9]+$/', null, $object->URLSegment) . '-' . $count;
            $count++;
        }

        Controller::curr()->getResponse()->addHeader('Content-Type', 'application/json');

        return Convert::raw2json([ 'value' => $object->URLSegment ]);
    }

    /**
     * @return SiteTree
     */
    public function getObject()
    {
        $controller = $this->getForm()->getController();
        $object = $controller->request->param('ModelClass');
        $idField = intval($controller->request->param('ID'));

        if(!$object && ($record = $controller->getRecord())) {
            $object = $record->ClassName;
        }

        return ($object && $idField) ? DataObject::get_by_id($object, $idField) : singleton($object);
    }

    /**
     * @param string $string The secondary text to show
     */
    public function setHelpText($string)
    {
        $this->helpText = $string;
    }

    /**
     * @return string the secondary text to show in the template
     */
    public function getHelpText()
    {
        return $this->helpText;

    }

    /**
     * @param the url that prefixes the page url segment field
     */
    public function setURLPrefix($url)
    {
        $this->urlPrefix = $url;
    }

    /**
     * @return the url prefixes the page url segment field to show in template
     */
    public function getURLPrefix()
    {
        return $this->urlPrefix;
    }

    public function getURLSuffix()
    {
        return $this->urlSuffix;
    }

    public function setURLSuffix($suffix)
    {
        $this->urlSuffix = $suffix;
    }

    public function Type()
    {
        return 'text urlsegment';
    }

    public function getURL()
    {
        return Controller::join_links($this->getURLPrefix(), $this->Value(), $this->getURLSuffix());
    }

}
