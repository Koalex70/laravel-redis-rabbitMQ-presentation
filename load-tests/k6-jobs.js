import http from "k6/http";
import { check, sleep } from "k6";

const BASE_URL = __ENV.BASE_URL || "http://host.docker.internal:8080";

export const options = {
  scenarios: {
    enqueue_load: {
      executor: "ramping-vus",
      startVUs: 1,
      stages: [
        { duration: "10s", target: 3 },
        { duration: "20s", target: 6 },
        { duration: "10s", target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_failed: ["rate<0.01"],
    http_req_duration: ["p(95)<1200"],
  },
};

export function setup() {
  http.post(`${BASE_URL}/api/v1/demo/metrics/reset`);
}

export default function () {
  const payload = JSON.stringify({
    report_type: "sales",
    from: "2026-04-01",
    to: "2026-04-24",
    user_id: 1,
  });

  const response = http.post(`${BASE_URL}/api/v1/jobs/report`, payload, {
    headers: { "Content-Type": "application/json" },
  });

  check(response, {
    "enqueue returns 202": (r) => r.status === 202,
    "job id exists": (r) => {
      try {
        const body = JSON.parse(r.body);
        return !!body?.data?.id;
      } catch (_) {
        return false;
      }
    },
  });

  sleep(0.15);
}
