<?php
namespace Forms\Controller;

class RestApi extends \LimeExtra\Controller {

    protected function before() {
        $this->app->response->mime = 'json';
    }
    
    public function submit($formname) {
        
        // Security check
        if ($formhash = $this->param("__csrf", false)) {

            if (!password_verify($formname, $formhash)) {
                return false;
            }

        } else {
            return false;
        }

        $frm = $this->module('forms')->form($formname);

        if (!$frm) {
            return false;
        }

        if ($formdata = $this->param("form", false)) {

            // custom form validation

            if ($this->path("#config:forms/{$formname}.php") && false === include($this->path("#config:forms/{$formname}.php"))) {
                return false;
            }

            if (isset($frm["email_forward"]) && $frm["email_forward"]) {

                $emails = array_map('trim', explode(',', $frm['email_forward']));
                $filtered_emails = [];

                foreach ($emails as $to) {

                    // Validate each email address individually, push if valid
                    if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                        $filtered_emails[] = $to;
                    }
                }

                if (count($filtered_emails)) {

                    $frm['email_forward'] = implode(',', $filtered_emails);

                    // There is an email template available
                    if ($template = $this->path("#config:forms/emails/{$formname}.php")) {

                        $body = $this->renderer->file($template, $formdata, false);

                        // Prepare template manually
                    } else {

                        $body = [];

                        foreach ($formdata as $key => $value) {
                            $body[] = "<b>{$key}:</b>\n<br>";
                            $body[] = (is_string($value) ? $value : json_encode($value)) . "\n<br>";
                        }

                        $body = implode("\n<br>", $body);
                    }

                    $options = $this->param('form_options', []);
                    $this->mailer->mail($frm['email_forward'],
                        $this->param("__mailsubject", "New form data for: " . $formname), $body, $options);
                }
            }

            if (isset($frm['save_entry']) && $frm['save_entry']) {

                $entry = ['data' => $formdata];
                $this->module('forms')->save($formname, $entry);
            }

            return json_encode($formdata);

        } else {
            return false;
        }
    }

    public function entries($name=null)
    {
        if (!$name) {
            return false;
        }

        if ($this->module('cockpit')->getUser()) {
            if (!$this->module('forms')->hasaccess($name, 'form')) {
                return $this->stop(401);
            }
        }

        $options = [];

        if ($filter   = $this->param('filter', null))  $options['filter'] = $filter;

        $content = $this->module("forms")->find($name, $options);

        return is_null($content) ? false : $content;
    }

}
