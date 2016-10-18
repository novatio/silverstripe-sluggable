<?php

class SluggableExtension extends DataExtension
{
    private static $db = [
        'URLSegment' => 'Varchar(255)',
    ];

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // If there is no URLSegment set, generate one from Title
        if ((!$this->owner->URLSegment || $this->owner->URLSegment == 'new-'.strtolower($this->owner->ClassName))
            && $this->owner->Title
        ) {
            $this->owner->URLSegment = $this->owner->generateURLSegment($this->owner->Title);
        } elseif ($this->owner->isChanged('URLSegment', 2)) {
            // Do a strict check on change level, to avoid double encoding caused by
            // bogus changes through forceChange()
            $filter = URLSegmentFilter::create();
            $this->owner->URLSegment = $filter->filter($this->owner->URLSegment);
            // If after sanitising there is no URLSegment, give it a reasonable default
            if (!$this->owner->URLSegment) {
                $this->owner->URLSegment = "listing-{$this->owner->ID}";
            }
        }

        // Ensure that this object has a non-conflicting URLSegment value.
        $count = 1;
        while (!$this->owner->validURLSegment($this->owner->URLSegment)) {
            $this->owner->URLSegment = preg_replace('/-[0-9]+$/', null, $this->owner->URLSegment) . '-' . $count;
            $count++;
        }
    }

    // TODO: fix preview functionality
    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->owner->ID) {
            $this->owner->URLSegment = 'new-' . strtolower($this->owner->ClassName);
        }

        if (class_exists('ObjectURLSegmentField')) {
            $baseLink = Controller::join_links (
                Director::absoluteBaseURL(),
                'slugpreview',
                $this->owner->ClassName,
                '/'
            );

            $urlsegment = new ObjectURLSegmentField("URLSegment", $this->owner->fieldLabel('URLSegment'));
            $urlsegment->setURLPrefix($baseLink);

            $fields->insertAfter('Title', $urlsegment);
        }
    }

    /**
     * Generate a URL segment based on the title provided.
     *
     * If {@link Extension}s wish to alter URL segment generation, they can do so by defining
     * updateURLSegment(&$url, $title).  $url will be passed by reference and should be modified.
     * $title will contain the title that was originally used as the source of this generated URL.
     * This lets extensions either start from scratch, or incrementally modify the generated URL.
     *
     * @param string $title Page title.
     *
     * @return string Generated url segment
     */
    public function generateURLSegment($title)
    {
        $filter = URLSegmentFilter::create();
        $t = $filter->filter($title);

        // Fallback to generic page name if path is empty (= no valid, convertable characters)
        if (!$t || $t == '-' || $t == '-1') {
            if (!$this->owner->ID && ($item = DataObject::get_one($this->owner->ClassName, "", true, "ID DESC"))) {
                $id = (int)$item->ID + 1;
                $t = strtolower($this->owner->ClassName) . "-$id";
            } else {
                $t = strtolower($this->owner->ClassName) . "-{$this->owner->ID}";
            }
        }

        return $t;
    }

    /**
     * Returns TRUE if this object has a URLSegment value that does not conflict with any other objects. This methods
     * checks for:
     *   - An option with the same URLSegment that has a conflict.
     *
     * @return bool
     */
    public function validURLSegment($URLSegment = null)
    {
        if (!$URLSegment) {
            $segment = Convert::raw2sql($this->owner->URLSegment);
        } else {
            $segment = Convert::raw2sql($URLSegment);
        }

        $sqlWhere = "\"{$this->owner->ClassName}\".\"URLSegment\" = '$segment'";
        if ($this->owner->ID) {
            $sqlWhere .= " AND \"{$this->owner->ClassName}\".\"ID\" != {$this->owner->ID}";
        }

        $existingOption = DataObject::get_one($this->owner->ClassName, $sqlWhere);

        return !($existingOption);
    }
}