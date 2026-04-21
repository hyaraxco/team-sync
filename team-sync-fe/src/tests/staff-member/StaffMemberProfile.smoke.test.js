import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

const loading = ref(false);
const performanceStatistics = ref({
  tasks_completed: 3,
  attendance_rate: 95,
  projects_count: 2,
  performance_score: 88,
});
const error = ref(null);
const fetchMyProfile = vi.fn();
const fetchPerformanceStatistics = vi.fn().mockResolvedValue(undefined);
const authUser = ref({
  name: "Agung Ramadhan",
  email: "agung@teamsync.com",
  profile_photo: null,
  employee_profile: {
    code: "EMP001",
    phone: "081234567890",
    city: "Jakarta",
    emergency_contacts: [],
  },
});

vi.mock("@/stores/staffMember", () => ({
  useStaffMemberStore: () => ({
    fetchMyProfile,
    fetchPerformanceStatistics,
    fetchMyTeamProjects: vi.fn().mockResolvedValue([]),
  }),
}));

vi.mock("@/stores/auth", () => ({
  useAuthStore: () => ({
    user: authUser.value,
  }),
}));

vi.mock("@/stores/task", () => ({
  useTaskStore: () => ({
    tasks: [],
    loading: false,
    fetchProjectTasks: vi.fn().mockResolvedValue(undefined),
  }),
}));

vi.mock("pinia", async (importOriginal) => {
  const actual = await importOriginal();

  return {
    ...actual,
    storeToRefs: (store) => {
      if (typeof store.fetchMyProfile === "function") {
        return {
          loading,
          performanceStatistics,
          error,
        };
      }

      return {
        user: authUser,
      };
    },
  };
});

import StaffMemberProfile from "@/views/staff-member/StaffMemberProfile.vue";

const factory = () =>
  mount(StaffMemberProfile, {
    global: {
      stubs: {
        RouterLink: {
          props: ["to"],
          template: '<a><slot /></a>',
        },
        StatusBadge: {
          props: ["value"],
          template: '<span>{{ value }}</span>',
        },
        AnimatedValue: {
          props: ["value", "suffix"],
          template: '<span>{{ value }}{{ suffix || "" }}</span>',
        },
      },
    },
  });

describe("StaffMemberProfile smoke", () => {
  beforeEach(() => {
    loading.value = false;
    error.value = null;
    performanceStatistics.value = {
      tasks_completed: 3,
      attendance_rate: 95,
      projects_count: 2,
      performance_score: 88,
    };
    fetchMyProfile.mockReset();
    fetchPerformanceStatistics.mockClear();
  });

  it("renders the fetched employee profile", async () => {
    fetchMyProfile.mockResolvedValue({
      id: 7,
      code: "EMP001",
      user: {
        name: "Agung Ramadhan",
        email: "agung@teamsync.com",
        profile_photo: null,
      },
      phone: "081234567890",
      city: "Jakarta",
      date_of_birth: "2000-01-01",
      gender: "male",
      place_of_birth: "Jakarta",
      address: "Jl. Sudirman",
      postal_code: "12345",
      job_information: {
        job_title: "Software Engineer",
        work_location: "remote",
        start_date: "2024-01-01",
        employment_type: "full_time",
        monthly_salary: 10000000,
        status: "active",
      },
      emergency_contacts: [
        {
          full_name: "Agung Emergency Contact",
          relationship: "Family",
          phone: "0811111111",
          email: "agung.emergency@teamsync.com",
        },
      ],
    });

    const wrapper = factory();
    await nextTick();
    await Promise.resolve();

    expect(wrapper.text()).toContain("Agung Ramadhan");
    expect(wrapper.text()).toContain("Agung Emergency Contact");
    expect(fetchPerformanceStatistics).toHaveBeenCalledWith(7);
  });

  it("falls back to auth user data when my-profile request fails", async () => {
    error.value = "Employee Profile Not Found";
    fetchMyProfile.mockRejectedValue(new Error("404"));

    const wrapper = factory();
    await nextTick();
    await Promise.resolve();

    expect(wrapper.text()).toContain("Agung Ramadhan");
    expect(wrapper.text()).toContain("Showing basic account information");
    expect(fetchPerformanceStatistics).not.toHaveBeenCalled();
  });
});
