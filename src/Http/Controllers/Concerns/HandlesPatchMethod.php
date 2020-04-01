<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Http\Controllers\Concerns;

use Proglum\JsonApi\Http\Exceptions\InvalidRequestException;
use Proglum\JsonApi\Http\Exceptions\ValidationException;
use Proglum\JsonApi\Http\JsonApiResponse;
use Proglum\JsonApi\Models\Exceptions\OperationNotImplemented;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Factory as ValidatorFactory;

/**
 * @method RestEndpoint endpoint()
 */
trait HandlesPatchMethod
{
    /** @var ValidatorFactory */
    protected $validatorFactory;

    protected $validationRules = [
        'operations' => 'required|array',
        'operations.*.op' => 'required|in:add,update,delete',
        'operations.*.data' => 'required',
        'operations.*.data.type' => 'required|string',
        'operations.*.data.id' =>
            'required_if:operations.*.op,update|required_if:operations.*.op,delete|alpha_dash',
        'operations.*.data.attributes' =>
            'required_if:operations.*.op,add|required_if:operations.*.op,update|array',
    ];

    /**
     * Set the validator factory
     *
     * @param ValidatorFactory $validatorFactory
     */
    public function setValidatorFactory(ValidatorFactory $validatorFactory)
    {
        $this->validatorFactory = $validatorFactory;
    }

    /**
     * Get validator factory
     *
     * @return ValidatorFactory
     */
    public function getValidatorFactory(): ValidatorFactory
    {
        if (!$this->validatorFactory) {
            $this->validatorFactory = app(ValidatorFactory::class);
        }
        return $this->validatorFactory;
    }

    /**
     * Add validation rule
     *
     * Creates key if doesn't exist, concatenates value if it does
     *
     * @param string $key
     * @param string $value
     */
    public function addValidationRule(string $key, string $value): void
    {
        if (!isset($this->validationRules[$key])) {
            $this->validationRules[$key] = $value;
        } else {
            $this->validationRules[$key] .= '|' . $value;
        }
    }

    /**
     * Get validation rules
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * Get parameter bag
     *
     * @param Request $request
     * @return ParameterBag
     */
    protected function getParameterBag(Request $request): ParameterBag
    {
        $parameterBag = $request->json();
        if ($parameterBag->count() == 0) {
            throw new ValidationException('Unable to decode body.');
        }
        return $parameterBag;
    }

    /**
     * Mass patch update
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function patch(Request $request): Response
    {
        $parameterBag = $this->getParameterBag($request);

        // Check operations is set
        $validator = $this->getValidatorFactory()->make($parameterBag->all(), $this->getValidationRules());
        if ($validator->fails()) {
            throw new ValidationException($validator->getMessageBag()->first());
        }

        $content = [];
        foreach ($parameterBag->get('operations') as $opNumber => $operation) {
            $content[] = $this->handleOperation($operation, $opNumber);
        }

        return new JsonApiResponse($content, Response::HTTP_OK);
    }

    /**
     * Handle an operation
     *
     * @param array $operation
     * @param int $opNumber
     * @return array
     * @throws Exception
     */
    protected function handleOperation(array $operation, int $opNumber): array
    {
        try {
            $op = $operation['op'];
            $request = new Request($operation);

            // Process op
            switch ($op) {
                // Add
                case 'add':
                    $jsonApiResponse = $this->store($request);
                    /** @var JsonApiResponse $jsonApiResponse */
                    $data = $jsonApiResponse->getOriginalContent();
                    return [
                        'op' => $op,
                        'status' => Response::HTTP_CREATED,
                        'data' => $data,
                    ];

                // Update
                case 'update':
                    $id = $operation['data']['id'];
                    /** @var JsonApiResponse $jsonApiResponse */
                    $jsonApiResponse = $this->update($request, $id);
                    $data = $jsonApiResponse->getOriginalContent();
                    return [
                        'op' => $op,
                        'status' => Response::HTTP_OK,
                        'data' => $data,
                    ];

                // Delete
                case 'delete':
                    $id = $operation['data']['id'];
                    $this->destroy($request, $id);
                    return [
                        'op' => $op,
                        'status' => Response::HTTP_NO_CONTENT,
                    ];

                default:
                    throw new LogicException(
                        'This should be impossible, if op is validated correctly. Op: ' . $op
                    );
            }

            // Catch any exception and display in a lovely format. This will allow the next op to run.
        } catch (ValidationException $exception) {
            return [
                'op' => (isset($operation['op'])) ? $operation['op'] : '',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'title' => 'ValidationException',
                'detail' => 'The operations.' . $opNumber . '.data.attributes: ' . $exception->getMessage(),
                'original_data' => $operation,
            ];
        } catch (ModelNotFoundException $exception) {
            return [
                'op' => (isset($operation['op'])) ? $operation['op'] : '',
                'status' => Response::HTTP_NOT_FOUND,
                'title' => 'Not Found Exception',
                'detail' => $exception->getMessage(),
                'original_data' => $operation,
            ];
        } catch (InvalidRequestException $exception) {
            return [
                'op' => $operation['op'],
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'title' => 'ValidationException',
                'detail' => 'The operations.' . $opNumber . '.data.attributes: ' . $exception->getMessage(),
                'original_data' => $operation,
            ];
        } catch (OperationNotImplemented $exception) {
            return [
                'op' => $operation['op'],
                'status' => Response::HTTP_NOT_IMPLEMENTED,
                'title' => 'OperationNotImplemented',
                'detail' => 'The operations.' . $opNumber . ' : ' . $exception->getMessage(),
                'original_data' => $operation,
            ];
        } catch (Exception $exception) {
            if (env('APP_DEBUG')) {
                throw $exception;
            }

            // Catch the exception and show the use a nice error message. This will allow the next PATCH message
            // to run
            return [
                'op' => (isset($operation['op'])) ? $operation['op'] : '',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'title' => 'Something went wrong',
                'detail' => 'Please check the log file to see the exception',
                'original_data' => $operation,
            ];
        }
    }
}
