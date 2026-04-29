import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useProjectStore } from '@/stores/project';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('Project Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useProjectStore();
        vi.clearAllMocks();
    });

    it('fetchProjects populates projects state', async () => {
        const params = { search: 'mobile app' };
        const mockResponse = {
            data: {
                data: [{ id: 1, name: 'Mobile App' }, { id: 2, name: 'HRIS API' }],
            },
        };
        axiosInstance.get.mockResolvedValueOnce(mockResponse);

        await store.fetchProjects(params);

        expect(axiosInstance.get).toHaveBeenCalledWith('projects', { params });
        expect(store.projects).toEqual(mockResponse.data.data);
        expect(store.loading).toBe(false);
    });

    it('fetchProjectById returns a single project by id', async () => {
        const mockProject = { id: 11, name: 'Payroll Revamp' };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: mockProject } });

        const result = await store.fetchProjectById(11);

        expect(axiosInstance.get).toHaveBeenCalledWith('projects/11');
        expect(result).toEqual(mockProject);
    });

    it('createProject calls POST multipart and sets success', async () => {
        const payload = {
            name: 'Project A',
            description: 'Important delivery',
            teams: [1, 2],
            photo_url: 'blob:http://localhost/preview-only',
        };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Project created',
                data: { id: 44, name: 'Project A' },
            },
        });

        await store.createProject(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith(
            'projects',
            expect.any(FormData),
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            },
        );
        expect(store.success).toBe('Project created');
        expect(store.loading).toBe(false);
    });

    it('updateProject calls POST multipart with method override and sets success', async () => {
        const payload = {
            name: 'Project B',
            teams: [3],
            photo_url: 'blob:http://localhost/preview-only',
        };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Project updated',
                data: { id: 55, name: 'Project B' },
            },
        });

        await store.updateProject(55, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith(
            'projects/55',
            expect.any(FormData),
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            },
        );
        expect(store.success).toBe('Project updated');
        expect(store.loading).toBe(false);
    });

    it('fetchProjectSquadSummary sets and returns squad summary', async () => {
        const mockSummary = {
            total_members: 7,
            members_by_role: [{ role: 'developer', total: 5 }],
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: mockSummary } });

        const result = await store.fetchProjectSquadSummary(99);

        expect(axiosInstance.get).toHaveBeenCalledWith('projects/99/squad-summary');
        expect(result).toEqual(mockSummary);
        expect(store.squadSummary).toEqual(mockSummary);
        expect(store.loadingSummary).toBe(false);
    });

    it('sets error on failure', async () => {
        const mockError = {
            response: {
                status: 400,
                data: { message: 'Invalid project request' },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchProjects({});

        expect(store.error).toBe('Invalid project request');
        expect(store.loading).toBe(false);
    });
});
