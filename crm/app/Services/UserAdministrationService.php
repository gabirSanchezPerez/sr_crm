<?php

namespace App\Services;

use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use InvalidArgumentException;
use RuntimeException;

final class UserAdministrationService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int> $unitIds
     */
    public function createUser(array $data, array $unitIds, int $actorId): int
    {
        $now = date('Y-m-d H:i:s');
        $data['contrasenia'] = password_hash((string) $data['contrasenia'], PASSWORD_DEFAULT);
        $data['u_crea'] = $actorId;
        $data['f_creacion'] = $now;
        $data['deleted'] = 0;

        $this->db->transStart();
        $model = new UserModel();
        $id = (int) $model->insert($data, true);
        $this->syncCommercialUnits($id, $unitIds, $actorId);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('No fue posible crear el usuario.');
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int> $unitIds
     */
    public function updateUser(int $id, array $data, array $unitIds, int $actorId): void
    {
        if (($data['contrasenia'] ?? '') !== '') {
            $data['contrasenia'] = password_hash((string) $data['contrasenia'], PASSWORD_DEFAULT);
        } else {
            unset($data['contrasenia']);
        }

        $data['u_modifica'] = $actorId;
        $data['f_modificacion'] = date('Y-m-d H:i:s');

        $this->db->transStart();
        (new UserModel())->update($id, $data);
        $this->syncCommercialUnits($id, $unitIds, $actorId);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('No fue posible actualizar el usuario.');
        }
    }

    public function deactivateUser(int $id, int $actorId): void
    {
        (new UserModel())->update($id, [
            'deleted' => 1,
            'u_modifica' => $actorId,
            'f_modificacion' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param list<int> $unitIds
     */
    private function syncCommercialUnits(int $userId, array $unitIds, int $actorId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('usuario_ucomercial')->where('usuario_id', $userId)->update([
            'deleted' => 1,
            'u_modifica' => $actorId,
            'f_modificacion' => $now,
        ]);

        foreach (array_values(array_unique(array_filter($unitIds))) as $unitId) {
            $this->db->table('usuario_ucomercial')->insert([
                'usuario_id' => $userId,
                'ucomercial_id' => $unitId,
                'u_crea' => $actorId,
                'u_modifica' => $actorId,
                'f_creacion' => $now,
                'f_modificacion' => $now,
                'deleted' => 0,
            ]);
        }
    }

    /**
     * @param array{A?: list<int>, CP?: list<int>} $accounts
     * @return array{transferred:int, skipped:int, results:list<array{type:string,id:int,before:int|null,after:int,status:string}>}
     */
    public function transferAccounts(int $fromUserId, int $toUserId, array $accounts, int $actorId): array
    {
        $accounts = [
            'A' => $this->normalizeAccountIds($accounts['A'] ?? []),
            'CP' => $this->normalizeAccountIds($accounts['CP'] ?? []),
        ];

        if ($fromUserId <= 0 || (new UserModel())->findActiveById($fromUserId) === null) {
            throw new InvalidArgumentException('El ejecutivo origen no esta activo.');
        }

        if ($fromUserId === $toUserId) {
            throw new InvalidArgumentException('Selecciona un ejecutivo destino diferente.');
        }

        if ($toUserId <= 0 || (new UserModel())->findActiveById($toUserId) === null) {
            throw new InvalidArgumentException('El ejecutivo destino no esta activo.');
        }

        if ($accounts['A'] === [] && $accounts['CP'] === []) {
            throw new InvalidArgumentException('Selecciona al menos una cuenta para transferir.');
        }

        $now = date('Y-m-d H:i:s');
        $transferred = 0;
        $skipped = 0;
        $results = [];

        $this->db->transBegin();

        try {
            foreach ([['A', 'cliente', 1], ['CP', 'cpotencial', 2]] as [$key, $table, $typeId]) {
                foreach ($accounts[$key] as $accountId) {
                    $row = $this->db->table($table)
                        ->select('id, ejecutivo_id')
                        ->where('id', $accountId)
                        ->where('deleted', 0)
                        ->get()
                        ->getRowArray();

                    if ($row === null || (int) $row['ejecutivo_id'] !== $fromUserId) {
                        $skipped++;
                        $results[] = [
                            'type' => (string) $key,
                            'id' => $accountId,
                            'before' => $row === null ? null : (int) $row['ejecutivo_id'],
                            'after' => $toUserId,
                            'status' => 'skipped',
                        ];
                        continue;
                    }

                    $audit = ['ejecutivo_id' => $toUserId, 'u_modifica' => $actorId, 'f_modificacion' => $now];
                    $this->db->table($table)->where('id', $accountId)->update($audit);
                    $this->db->table('seguimiento')
                        ->where('tipo_id', $typeId)
                        ->where('cliente_id', $accountId)
                        ->where('deleted', 0)
                        ->update($audit);

                    $transferred++;
                    $results[] = [
                        'type' => (string) $key,
                        'id' => $accountId,
                        'before' => $fromUserId,
                        'after' => $toUserId,
                        'status' => 'transferred',
                    ];
                }
            }

            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw new RuntimeException('No fue posible transferir las cuentas.', 0, $exception);
        }

        return ['transferred' => $transferred, 'skipped' => $skipped, 'results' => $results];
    }

    /**
     * @param list<int>|array<int|string, mixed> $ids
     * @return list<int>
     */
    private function normalizeAccountIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }
}