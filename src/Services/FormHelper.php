<?php

namespace Wolfmatrix\LaravelCrud\Services;

use Symfony\Component\Form\FormInterface;

class FormHelper
{
    private $fields = [];

    public function getErrorsFromForm(FormInterface $form)
    {
        $errors = [];
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }
        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsFromForm($childForm)) {
                    if (in_array($childForm->getName(), $this->fields)) {
                        $errors[$childForm->getName()]       = [];
                        $errors[$childForm->getName()]['id'] = $childErrors;
                    } else {
                        $errors[$childForm->getName()] = $childErrors;
                    }
                }
            }
        }
        return $errors;
    }
}