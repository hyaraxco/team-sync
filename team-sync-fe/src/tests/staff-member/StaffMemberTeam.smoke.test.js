import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const fetchMyTeam = vi.fn();
const fetchMyTeamMembers = vi.fn();
const fetchMyTeamProjects = vi.fn();

vi.mock("@/stores/staffMember", () => ({
  useStaffMemberStore: () => ({
    fetchMyTeam,
    fetchMyTeamMembers,
    fetchMyTeamProjects,
  }),
}));

import StaffMemberTeam from "@/views/staff-member/StaffMemberTeam.vue";

const factory = () =>
  mount(StaffMemberTeam, {
    global: {
      stubs: {
        AnimatedValue: {
          props: ["value"],
          template: "<span>{{ value }}</span>",
        },
        CardList: {
          props: ["data"],
          template: '<div class="card-list-stub">{{ data?.name || data?.title }}</div>',
        },
      },
    },
  });

const flushAsync = async () => {
  await Promise.resolve();
  await Promise.resolve();
};

describe("StaffMemberTeam smoke", () => {
  beforeEach(() => {
    fetchMyTeam.mockReset();
    fetchMyTeamMembers.mockReset();
    fetchMyTeamProjects.mockReset();
  });

  it("renders team workspace when employee has a team", async () => {
    fetchMyTeam.mockResolvedValue({
      id: 10,
      name: "Alpha Team",
      status: "active",
      department: "Engineering",
      created_at: "2026-04-01T00:00:00.000000Z",
      expected_size: 8,
      leader: { id: 1, name: "Yudhis" },
      responsibilities: ["Build products"],
    });
    fetchMyTeamMembers.mockResolvedValue([
      {
        id: 1,
        joined_at: "2026-04-01T00:00:00.000000Z",
        staff_member: {
          user: { id: 1, name: "Yudhis" },
          jobInformation: { job_title: "Manager" },
        },
      },
    ]);
    fetchMyTeamProjects.mockResolvedValue([
      {
        id: 90,
        name: "Payroll Revamp",
      },
    ]);

    const wrapper = factory();
    await flushAsync();

    expect(wrapper.text()).toContain("Alpha Team");
    expect(wrapper.find('[data-testid="my-team-empty"]').exists()).toBe(false);
    expect(fetchMyTeamMembers).toHaveBeenCalledTimes(1);
    expect(fetchMyTeamProjects).toHaveBeenCalledTimes(1);
  });

  it("shows explicit empty state when user is not assigned to any team", async () => {
    fetchMyTeam.mockRejectedValue({
      response: {
        data: {
          message: "Internal Server Error: You are not assigned to any team",
        },
      },
    });
    fetchMyTeamMembers.mockResolvedValue([]);
    fetchMyTeamProjects.mockResolvedValue([]);

    const wrapper = factory();
    await flushAsync();

    expect(wrapper.find('[data-testid="my-team-empty"]').exists()).toBe(true);
    expect(wrapper.text()).toContain("You are not assigned to a team yet");
    expect(wrapper.text()).toContain("Please contact HR or your manager");
    expect(fetchMyTeamMembers).not.toHaveBeenCalled();
    expect(fetchMyTeamProjects).not.toHaveBeenCalled();
  });
});
