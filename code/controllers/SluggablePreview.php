<?php

/**
 * Class SluggablePreview
 */
class SluggablePreview extends Controller implements PermissionProvider
{
    /**
     * Template thats used to render the pages.
     *
     * @var string
     * @config
     */
    private static $template_main = 'Page';

    /**
     * Default URL handlers - index/($Object)/(ID)/(OtherID)
     */
    private static $url_handlers = [
        '//$Object/$ID/$OtherID' => 'index',
    ];

    public function index()
    {
        if (($object = Convert::raw2sql($this->request->param('Object'))) &&
            ($urlSegment = Convert::raw2sql($this->request->param('ID'))) &&
            class_exists($object) &&
            ($item = DataObject::get_one($object, "URLSegment = '{$urlSegment}'")) &&
            ($controller = $this->getResponseController($item))
        ) {
            /*
             * If the controller calls Director::redirect(), this will break early
             */
            if (($response = $controller->getResponse()) && $response->isFinished()) {
                return $response;
            }

            /*
             * Customise the controller if properly configured.
             */
            if ($customContent = Config::inst()->get($item->ClassName, 'preview_content')) {
                /*
                 * Parse the data from item. Special case = "self".
                 */
                array_walk($customContent, function(&$value, $key, $item) {
                    if (strtolower($value) == 'self') {
                        $value = $item;
                    } else {
                        $value = $item->getField($value);
                    }
                }, $item);

                /*
                 * Customise the controller with parsed data
                 */
                $controller = $controller->customise([
                    'CurrentJob' => $item
                ]);
            }
            // Return the customised controller
            return $controller->renderWith([
                Config::inst()->get($item->ClassName, 'preview_template'),
                $this->stat('template_main')
            ]);
        }

        return [];
    }

    /**
     * @return array
     */
    public function providePermissions()
    {
        return [
            "CMS_ACCESS_SlugPreview" => [
                'name'     => _t('Sluggable.ADMIN_PERMISSION', "Access to 'Slug Preview'"),
                'category' => _t('Sluggable.CMS_ACCESS_CATEGORY', 'Sluggable'),
            ],
        ];
    }

    /**
     * Prepare the controller for handling the response to this request
     * 'Borrowed' from @see Security
     *
     * @param string $title Title to use
     *
     * @return Controller
     */
    protected function getResponseController($item)
    {
        if (!class_exists('SiteTree')) {
            return $this;
        }

        // Use sitetree pages to render the preview page
        $tmpPage = new Page();
        $tmpPage->Title = $item->Title;
        $tmpPage->URLSegment = $item->URLSegment;
        // Disable ID-based caching  of the log-in page by making it a random number
        $tmpPage->ID = -1 * rand(1, 10000000);

        $controller = Page_Controller::create($tmpPage);
        $controller->setDataModel($item);
        $controller->init();

        return $controller;
    }
}