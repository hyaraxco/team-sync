import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { usePerformanceGoalStore } from '@/stores/performanceGoal';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('Performance Goal Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = usePerformanceGoalStore();
        vi.clearAllMocks();
    });

    it('fetchMyGoals populates myGoals and pagination', async () => {
        const filters = { status: 'on_track' };
        const paginatedData = {
            data: [{ id: 1, title: 'Increase retention' }],
            current_page: 2,
            per_page: 10,
            total: 30,
            last_page: 3,
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: paginatedData } });

        const result = await store.fetchMyGoals(filters);

        expect(axiosInstance.get).toHaveBeenCalledWith('/performance/goals/my-goals', { params: filters });
        expect(store.myGoals).toEqual(paginatedData.data);
        expect(store.pagination).toEqual({
            current_page: 2,
            per_page: 10,
            total: 30,
            last_page: 3,
        });
        expect(result).toEqual(paginatedData);
        expect(store.goalsLoading).toBe(false);
    });

    it('fetchMyGoals throws and sets error on failure', async () => {
        const mockError = { response: { data: { message: 'Failed to fetch my goals' } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchMyGoals()).rejects.toEqual(mockError);
        expect(store.error).toBe('Failed to fetch my goals');
        expect(store.goalsLoading).toBe(false);
    });

    it('fetchTeamGoals populates teamGoals and pagination', async () => {
        const filters = { department: 'Engineering' };
        const paginatedData = {
            data: [{ id: 2, title: 'Improve cycle time' }],
            current_page: 1,
            per_page: 15,
            total: 1,
            last_page: 1,
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: paginatedData } });

        const result = await store.fetchTeamGoals(filters);

        expect(axiosInstance.get).toHaveBeenCalledWith('/performance/goals/team-goals', { params: filters });
        expect(store.teamGoals).toEqual(paginatedData.data);
        expect(result).toEqual(paginatedData);
        expect(store.goalsLoading).toBe(false);
    });

    it('fetchTeamGoals throws and sets error on failure', async () => {
        const mockError = { response: { data: { message: 'Failed to fetch team goals' } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchTeamGoals()).rejects.toEqual(mockError);
        expect(store.error).toBe('Failed to fetch team goals');
        expect(store.goalsLoading).toBe(false);
    });

    it('fetchGoalById sets currentGoal and returns it', async () => {
        const goal = { id: 9, title: 'Reduce bugs' };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: goal } });

        const result = await store.fetchGoalById(9);

        expect(axiosInstance.get).toHaveBeenCalledWith('/performance/goals/9');
        expect(store.currentGoal).toEqual(goal);
        expect(result).toEqual(goal);
        expect(store.goalsLoading).toBe(false);
    });

    it('fetchGoalById throws and sets error on failure', async () => {
        const mockError = { response: { data: { message: 'Goal not found' } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchGoalById(999)).rejects.toEqual(mockError);
        expect(store.error).toBe('Goal not found');
        expect(store.goalsLoading).toBe(false);
    });

    it('createGoal adds new goal at start and marks success', async () => {
        store.myGoals = [{ id: 10, title: 'Existing goal' }];
        const payload = { title: 'New goal' };
        const createdGoal = { id: 11, title: 'New goal' };
        axiosInstance.post.mockResolvedValueOnce({ data: { data: createdGoal } });

        const result = await store.createGoal(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('/performance/goals', payload);
        expect(result).toEqual(createdGoal);
        expect(store.myGoals[0]).toEqual(createdGoal);
        expect(store.success).toBe(true);
        expect(store.goalsLoading).toBe(false);
    });

    it('createGoal throws and sets error on failure', async () => {
        const mockError = { response: { data: { message: 'Goal create failed' } } };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.createGoal({ title: 'x' })).rejects.toEqual(mockError);
        expect(store.error).toBe('Goal create failed');
        expect(store.success).toBe(false);
        expect(store.goalsLoading).toBe(false);
    });

    it('updateGoal updates myGoals, teamGoals, currentGoal and marks success', async () => {
        const updatedGoal = { id: 22, title: 'Updated goal' };
        store.myGoals = [{ id: 22, title: 'Old my goal' }];
        store.teamGoals = [{ id: 22, title: 'Old team goal' }];
        store.currentGoal = { id: 22, title: 'Old current goal' };
        axiosInstance.put.mockResolvedValueOnce({ data: { data: updatedGoal } });

        const result = await store.updateGoal(22, { title: 'Updated goal' });

        expect(axiosInstance.put).toHaveBeenCalledWith('/performance/goals/22', { title: 'Updated goal' });
        expect(result).toEqual(updatedGoal);
        expect(store.myGoals[0]).toEqual(updatedGoal);
        expect(store.teamGoals[0]).toEqual(updatedGoal);
        expect(store.currentGoal).toEqual(updatedGoal);
        expect(store.success).toBe(true);
        expect(store.goalsLoading).toBe(false);
    });

    it('updateGoal throws and sets error on failure', async () => {
        const mockError = { response: { data: { message: 'Goal update failed' } } };
        axiosInstance.put.mockRejectedValueOnce(mockError);

        await expect(store.updateGoal(22, { title: 'x' })).rejects.toEqual(mockError);
        expect(store.error).toBe('Goal update failed');
        expect(store.success).toBe(false);
        expect(store.goalsLoading).toBe(false);
    });

    it('deleteGoal removes goal from myGoals and teamGoals', async () => {
        store.myGoals = [{ id: 33, title: 'Delete me' }, { id: 34, title: 'Keep me' }];
        store.teamGoals = [{ id: 33, title: 'Delete me' }, { id: 35, title: 'Keep me too' }];
        axiosInstance.delete.mockResolvedValueOnce({ data: {} });

        const result = await store.deleteGoal(33);

        expect(axiosInstance.delete).toHaveBeenCalledWith('/performance/goals/33');
        expect(result).toBe(true);
        expect(store.myGoals).toEqual([{ id: 34, title: 'Keep me' }]);
        expect(store.teamGoals).toEqual([{ id: 35, title: 'Keep me too' }]);
        expect(store.goalsLoading).toBe(false);
    });

    it('deleteGoal throws and sets error on failure', async () => {
        const mockError = { response: { data: { message: 'Goal delete failed' } } };
        axiosInstance.delete.mockRejectedValueOnce(mockError);

        await expect(store.deleteGoal(33)).rejects.toEqual(mockError);
        expect(store.error).toBe('Goal delete failed');
        expect(store.goalsLoading).toBe(false);
    });

    it('addProgressUpdate prepends update, refreshes goal, and marks success', async () => {
        const progressUpdate = { id: 1, progress: 30 };
        const refreshedGoal = { id: 44, title: 'Goal 44', progress: 30 };
        axiosInstance.post.mockResolvedValueOnce({ data: { data: progressUpdate } });
        axiosInstance.get.mockResolvedValueOnce({ data: { data: refreshedGoal } });

        const result = await store.addProgressUpdate(44, { progress: 30 });

        expect(axiosInstance.post).toHaveBeenCalledWith('/performance/goals/44/update-progress', { progress: 30 });
        expect(axiosInstance.get).toHaveBeenCalledWith('/performance/goals/44');
        expect(result).toEqual(progressUpdate);
        expect(store.goalUpdates[0]).toEqual(progressUpdate);
        expect(store.currentGoal).toEqual(refreshedGoal);
        expect(store.success).toBe(true);
        expect(store.updatesLoading).toBe(false);
    });

    it('addProgressUpdate throws and sets error on failure', async () => {
        const mockError = { response: { data: { message: 'Progress update failed' } } };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.addProgressUpdate(44, { progress: 50 })).rejects.toEqual(mockError);
        expect(store.error).toBe('Progress update failed');
        expect(store.success).toBe(false);
        expect(store.updatesLoading).toBe(false);
    });

    it('fetchProgressUpdates populates goalUpdates and returns data', async () => {
        const updates = [{ id: 1, note: 'Week 1' }, { id: 2, note: 'Week 2' }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: updates } });

        const result = await store.fetchProgressUpdates(88);

        expect(axiosInstance.get).toHaveBeenCalledWith('/performance/goals/88/updates');
        expect(store.goalUpdates).toEqual(updates);
        expect(result).toEqual(updates);
        expect(store.updatesLoading).toBe(false);
    });

    it('fetchProgressUpdates throws and sets error on failure', async () => {
        const mockError = { response: { data: { message: 'Progress fetch failed' } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchProgressUpdates(88)).rejects.toEqual(mockError);
        expect(store.error).toBe('Progress fetch failed');
        expect(store.updatesLoading).toBe(false);
    });
});
