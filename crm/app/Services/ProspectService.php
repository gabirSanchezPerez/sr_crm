<?php

namespace App\Services;

use App\Models\CustomerModel;
use App\Models\ProspectModel;
use App\Models\UserModel;
use RuntimeException;

final class ProspectService
{
    public function __construct(private readonly ProspectModel $model = new ProspectModel())
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(array $identity, string $scope, ?string $search = null): array
    {
        return $this->model->activeRows($identity, $scope, $search);
    }

    public function find(int $id, array $identity, string $scope): ?array
    {
        return $this->model->activeById($id, $identity, $scope);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input, int $actorId): int
    {
        $data = $this->payload($input);
        $this->guardDuplicate($data['razon_social'], $data['marca']);

        $data['cliente_id'] = null;
        $data['deleted'] = 0;
        $data['_countries_id'] = (int) ($data['_countries_id'] ?? 42);
        $data['u_crea'] = $actorId;
        $data['f_creacion'] = date('Y-m-d H:i:s');

        $id = $this->model->insert($data, true);
        if ($id === false) {
            throw new RuntimeException('No fue posible crear el prospecto.');
        }

        return (int) $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input, int $actorId): void
    {
        $data = $this->payload($input);
        $this->guardDuplicate($data['razon_social'], $data['marca'], $id);

        $data['u_modifica'] = $actorId;
        $data['f_modificacion'] = date('Y-m-d H:i:s');

        if (! $this->model->update($id, $data)) {
            throw new RuntimeException('No fue posible actualizar el prospecto.');
        }
    }

    /**
     * @return array{cliente_id:int, created:bool}
     */
    public function convertToCustomer(int $id, array $identity, string $scope, int $actorId): array
    {
        $db = db_connect();
        $customerModel = new CustomerModel();
        $userModel = new UserModel();

        $db->transBegin();

        try {
            $prospect = $this->find($id, $identity, $scope);
            if ($prospect === null) {
                throw new RuntimeException('Prospecto no encontrado.');
            }

            if (trim((string) ($prospect['rfc'] ?? '')) === '') {
                throw new RuntimeException('El prospecto requiere RFC para convertirse en cliente.');
            }

            $executive = $userModel->findActiveById((int) $prospect['ejecutivo_id']);
            if ($executive === null) {
                throw new RuntimeException('El ejecutivo del prospecto no esta activo o no existe.');
            }

            $managementId = (int) ($executive['cgestion_id'] ?? 0);
            if ($managementId <= 0) {
                throw new RuntimeException('El ejecutivo del prospecto no tiene canal de gestion.');
            }

            $duplicate = $customerModel->builder()
                ->select('id')
                ->where('deleted', 0)
                ->where('cgestion_id', $managementId)
                ->where('razon_social', (string) $prospect['razon_social'])
                ->where('marca', (string) $prospect['marca'])
                ->get()
                ->getRowArray();

            $created = false;
            if ($duplicate !== null) {
                $customerId = (int) $duplicate['id'];
            } else {
                $customerId = (int) $customerModel->insert([
                    'razon_social' => (string) $prospect['razon_social'],
                    'rfc' => strtoupper(trim((string) $prospect['rfc'])),
                    'sector_id' => (int) ($prospect['sector_id'] ?? 0),
                    'cpotencial_id' => $id,
                    'marca' => (string) $prospect['marca'],
                    'cgestion_id' => $managementId,
                    'ejecutivo_id' => (int) $prospect['ejecutivo_id'],
                    'u_crea' => $actorId,
                    'f_creacion' => date('Y-m-d H:i:s'),
                    'deleted' => 0,
                    '_countries_id' => (int) ($prospect['_countries_id'] ?? 42),
                    'estado' => (string) ($prospect['estado'] ?? ''),
                    'ciudad' => (string) ($prospect['ciudad'] ?? ''),
                    'cp' => (string) ($prospect['cp'] ?? ''),
                    'direccion' => (string) ($prospect['direccion'] ?? ''),
                ], true);

                if ($customerId <= 0) {
                    throw new RuntimeException('No fue posible crear el cliente.');
                }

                $created = true;
            }

            if (! $this->model->update($id, [
                'cliente_id' => $customerId,
                'u_modifica' => $actorId,
                'f_modificacion' => date('Y-m-d H:i:s'),
            ])) {
                throw new RuntimeException('No fue posible vincular el prospecto convertido.');
            }

            $db->transCommit();

            return ['cliente_id' => $customerId, 'created' => $created];
        } catch (RuntimeException $exception) {
            $db->transRollback();
            throw $exception;
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw new RuntimeException('No fue posible convertir el prospecto.', 0, $exception);
        }
    }

    public function softDelete(int $id, int $actorId): void
    {
        if (! $this->model->update($id, [
            'deleted' => 1,
            'u_modifica' => $actorId,
            'f_modificacion' => date('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('No fue posible desactivar el prospecto.');
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function payload(array $input): array
    {
        return [
            'razon_social' => trim((string) ($input['razon_social'] ?? '')),
            'marca' => trim((string) ($input['marca'] ?? '')),
            'rfc' => strtoupper(trim((string) ($input['rfc'] ?? ''))),
            'sector_id' => (int) ($input['sector_id'] ?? 0),
            'ejecutivo_id' => (int) ($input['ejecutivo_id'] ?? 0),
            '_countries_id' => (int) ($input['_countries_id'] ?? 42),
            'estado' => trim((string) ($input['estado'] ?? '')),
            'ciudad' => trim((string) ($input['ciudad'] ?? '')),
            'cp' => trim((string) ($input['cp'] ?? '')),
            'direccion' => trim((string) ($input['direccion'] ?? '')),
        ];
    }

    private function guardDuplicate(string $businessName, string $brand, ?int $exceptId = null): void
    {
        if ($this->model->duplicateExists($businessName, $brand, $exceptId)) {
            throw new RuntimeException('Ya existe un prospecto activo con la misma razon social y marca.');
        }
    }
}
