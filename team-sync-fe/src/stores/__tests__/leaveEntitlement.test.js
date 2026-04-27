import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useLeaveEntitlementStore } from '../leaveEntitlement';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
  axiosInstance: {
    get: vi.fn(),
    put: vi.fn(),
  }
}));

describe('LeaveEntitlement Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('should fetch entitlements correctly', async () => {
    const store = useLeaveEntitlementStore();
    const mockResponse = { 
      data: { 
        data: { 
          items: [{ id: 1, leave_type: 'annual' }],
          grouped: { full_time: [{ id: 1, leave_type: 'annual' }] }
        } 
      } 
    };
    axiosInstance.get.mockResolvedValueOnce(mockResponse);

    await store.fetchEntitlements();

    expect(axiosInstance.get).toHaveBeenCalledWith('leave-entitlements');
    expect(store.entitlements).toEqual(mockResponse.data.data.items);
    expect(store.groupedEntitlements).toEqual(mockResponse.data.data.grouped);
    expect(store.loading).toBe(false);
    expect(store.error).toBe(null);
  });

  it('should handle fetch errors gracefully', async () => {
    const store = useLeaveEntitlementStore();
    const mockError = new Error('Network Error');
    axiosInstance.get.mockRejectedValueOnce(mockError);

    await store.fetchEntitlements();

    expect(axiosInstance.get).toHaveBeenCalledWith('leave-entitlements');
    expect(store.entitlements).toEqual([]);
    expect(store.groupedEntitlements).toEqual({});
    expect(store.loading).toBe(false);
    expect(store.error).toBe('Network Error');
  });

  it('should update entitlement correctly', async () => {
    const store = useLeaveEntitlementStore();
    const mockResponse = { data: { data: { id: 1, quota_days: 20 } } };
    axiosInstance.put.mockResolvedValueOnce(mockResponse);
    axiosInstance.get.mockResolvedValueOnce({ 
      data: { data: { items: [], grouped: {} } } 
    });

    const payload = { quota_days: 20 };
    const result = await store.updateEntitlement(1, payload);

    expect(axiosInstance.put).toHaveBeenCalledWith('leave-entitlements/1', payload);
    expect(result).toEqual(mockResponse.data.data);
    expect(store.loading).toBe(false);
    expect(store.error).toBe(null);
  });

  it('should handle update error gracefully', async () => {
    const store = useLeaveEntitlementStore();
    const mockError = new Error('Network Error');
    axiosInstance.put.mockRejectedValueOnce(mockError);

    try {
      await store.updateEntitlement(1, { quota_days: 20 });
    } catch (e) {
      expect(e).toBe(mockError);
    }

    expect(axiosInstance.put).toHaveBeenCalledWith('leave-entitlements/1', { quota_days: 20 });
    expect(store.loading).toBe(false);
    expect(store.error).toBe('Network Error');
  });
});
