<?php
declare(strict_types=1);

namespace Silver\Core;

class Controller
{
    protected ?string $controllerName = null;
    protected ?string $modelNamespace = null;

    public function __construct()
    {
        if ($this->controllerName !== null) {
            $modelPath = ROOT . 'App' . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . ucfirst($this->controllerName) . 'Model.php';

            if (file_exists($modelPath)) {
                $this->modelNamespace = "\\App\\Models\\" . ucfirst($this->controllerName) . 'Model';
            } else {
                throw new \Exception(sprintf('%s model file not found', $this->controllerName));
            }
        }
    }

    protected function model(?string $model = null): ?string
    {
        if ($model !== null) {
            $this->modelNamespace = $model;
        }
        return $this->modelNamespace;
    }
}
