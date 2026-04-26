import http from "k6/http";
import { check, sleep } from "k6";

const BASE_URL = __ENV.BASE_URL || "http://host.docker.internal:8080";
const PRODUCT_ID = __ENV.PRODUCT_ID || "1";

export const options = {
  scenarios: {
    cache_read_load: {
      executor: "ramping-vus",
      startVUs: 1,
      stages: [
        { duration: "10s", target: 8 },
        { duration: "20s", target: 12 },
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
  http.post(`${BASE_URL}/api/v1/demo/cache/flush`);
  http.post(`${BASE_URL}/api/v1/demo/metrics/reset`);

  const warmup = http.get(`${BASE_URL}/api/v1/products/${PRODUCT_ID}`);
  check(warmup, {
    "warmup returns 200": (r) => r.status === 200,
  });
}

export default function () {
  const response = http.get(`${BASE_URL}/api/v1/products/${PRODUCT_ID}`);

  check(response, {
    "status 200": (r) => r.status === 200,
    "has x-cache header": (r) => !!r.headers["X-Cache"],
  });

  sleep(0.2);
}
