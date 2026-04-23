import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { usePerformanceReviewStore } from '../performanceReview';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
  axiosInstance: {
    post: vi.fn(),
    put: vi.fn(),
  }
}));

describe('PerformanceReview Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('should call generate-reviews API correctly', async () => {
    const store = usePerformanceReviewStore();
    const mockResponse = { data: { data: { generated_count: 5 } } };
    axiosInstance.post.mockResolvedValueOnce(mockResponse);

    const result = await store.generateReviews(1);

    expect(axiosInstance.post).toHaveBeenCalledWith('/performance/cycles/1/generate-reviews');
    expect(result).toEqual({ generated_count: 5 });
    expect(store.cyclesLoading).toBe(false);
  });

  it('should call assign-reviewer API correctly', async () => {
    const store = usePerformanceReviewStore();
    const mockResponse = { data: { data: { id: 10, reviewer_id: 2 } } };
    axiosInstance.put.mockResolvedValueOnce(mockResponse);

    const result = await store.assignReviewer(10, 2);

    expect(axiosInstance.put).toHaveBeenCalledWith('/performance/reviews/10/assign-reviewer', {
      reviewer_id: 2
    });
    expect(result).toEqual({ id: 10, reviewer_id: 2 });
  });
});
