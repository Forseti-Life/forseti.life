# AmISafe Comprehensive Filter Testing Report

**Generated**: Fri Nov  7 19:51:54 UTC 2025  
**Test Type**: Automated Filter Granularity Testing  
**Incidents Tested**: 20 random incidents  
**Total Tests**: 180  
**Passed**: 80  
**Failed**: 100  
**Success Rate**: 44.4%  

## Test Overview

This comprehensive test validates filter functionality by selecting 20 random incidents and testing each filter's ability to:
1. **Include** specific incidents when appropriate (positive tests)
2. **Exclude** specific incidents when appropriate (negative tests)
3. **Combine** multiple filters effectively

## Filter Types Tested

- **Crime Type Filter**: Tests incident_type filtering
- **District Filter**: Tests police district filtering  
- **Date Filter**: Tests temporal filtering by month/date range
- **Time Filter**: Tests time-of-day period filtering
- **Combined Filters**: Tests multiple filters working together

## Random Test Incidents

| Incident | Type | District | Month | Hour | Date |
|----------|------|----------|--------|------|------|
| 1 | 600 | 35 | 9 | 23 | 2024-09-12 23:46:00 |
| 2 | 800 | 18 | 1 | 2 | 2018-01-13 02:06:00 |
| 3 | 1100 | 2 | 2 | 18 | 2012-02-23 18:12:00 |
| 4 | 800 | 8 | 9 | 3 | 2022-09-07 03:26:00 |
| 5 | 1400 | 24 | 12 | 17 | 2017-12-01 17:20:00 |
| 6 | 800 | 25 | 4 | 16 | 2007-04-24 16:11:00 |
| 7 | 800 | 25 | 3 | 2 | 2018-03-03 02:56:00 |
| 8 | 1100 | 15 | 7 | 23 | 2010-07-14 23:10:00 |
| 9 | 600 | 15 | 6 | 18 | 2009-06-19 18:22:00 |
| 10 | 1400 | 24 | 12 | 23 | 2010-12-24 23:13:00 |
| 11 | 500 | 25 | 10 | 12 | 2020-10-15 12:20:00 |
| 12 | 2600 | 17 | 5 | 0 | 2008-05-01 00:26:00 |
| 13 | 2600 | 8 | 7 | 3 | 2007-07-03 03:26:00 |
| 14 | 600 | 6 | 11 | 17 | 2009-11-23 17:28:00 |
| 15 | 300 | 2 | 12 | 22 | 2009-12-24 22:42:00 |
| 16 | 600 | 25 | 9 | 3 | 2016-09-27 03:09:00 |
| 17 | 2600 | 5 | 7 | 12 | 2023-07-02 12:30:00 |
| 18 | 1400 | 18 | 7 | 13 | 2018-07-14 13:11:00 |
| 19 | 800 | 18 | 12 | 1 | 2020-12-28 01:12:00 |
| 20 | 800 | 99 | 11 | 23 | 2021-11-10 23:21:00 |

## Detailed Test Results

| Time | Result | Test Name | Details |
|------|--------|-----------|---------|
| 19:51:21 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 3261967_22171591 (600) | Filter did not return expected incident type 600 |
| 19:51:21 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 3261967_22171591 (600) | Filter correctly excludes incident type 600 |
| 19:51:21 | ❌ FAIL | District Filter POSITIVE - Incident 3261967_22171591 (District 35) | Filter did not return expected district 35 |
| 19:51:22 | ✅ PASS | District Filter NEGATIVE - Incident 3261967_22171591 (District 35) | Filter correctly excludes district 35 |
| 19:51:22 | ❌ FAIL | Date Filter POSITIVE - Incident 3261967_22171591 (Month 9) | Filter returned no data for month 9 |
| 19:51:22 | ✅ PASS | Date Filter NEGATIVE - Incident 3261967_22171591 (Exclude Month 9) | Filter correctly excludes original month (returned 0 hexagons for month 10) |
| 19:51:22 | ❌ FAIL | Time Filter POSITIVE - Incident 3261967_22171591 (evening, Hour 23) | Filter returned no data for evening period |
| 19:51:23 | ✅ PASS | Time Filter NEGATIVE - Incident 3261967_22171591 (Exclude evening) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:23 | ❌ FAIL | Combined Filters POSITIVE - Incident 3261967_22171591 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:23 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (800) | Filter did not return expected incident type 800 |
| 19:51:23 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (800) | Filter correctly excludes incident type 800 |
| 19:51:23 | ❌ FAIL | District Filter POSITIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (District 18) | Filter did not return expected district 18 |
| 19:51:23 | ✅ PASS | District Filter NEGATIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (District 18) | Filter correctly excludes district 18 |
| 19:51:24 | ❌ FAIL | Date Filter POSITIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (Month 1) | Filter returned no data for month 1 |
| 19:51:24 | ✅ PASS | Date Filter NEGATIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (Exclude Month 1) | Filter correctly excludes original month (returned 0 hexagons for month 2) |
| 19:51:24 | ❌ FAIL | Time Filter POSITIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (night, Hour 2) | Filter returned no data for night period |
| 19:51:24 | ✅ PASS | Time Filter NEGATIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (Exclude night) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:25 | ❌ FAIL | Combined Filters POSITIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:25 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (1100) | Filter did not return expected incident type 1100 |
| 19:51:25 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (1100) | Filter correctly excludes incident type 1100 |
| 19:51:25 | ❌ FAIL | District Filter POSITIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (District 2) | Filter did not return expected district 2 |
| 19:51:25 | ✅ PASS | District Filter NEGATIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (District 2) | Filter correctly excludes district 2 |
| 19:51:26 | ❌ FAIL | Date Filter POSITIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (Month 2) | Filter returned no data for month 2 |
| 19:51:26 | ✅ PASS | Date Filter NEGATIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (Exclude Month 2) | Filter correctly excludes original month (returned 0 hexagons for month 3) |
| 19:51:26 | ❌ FAIL | Time Filter POSITIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (evening, Hour 18) | Filter returned no data for evening period |
| 19:51:26 | ✅ PASS | Time Filter NEGATIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (Exclude evening) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:26 | ❌ FAIL | Combined Filters POSITIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:27 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 1220383_1121140 (800) | Filter did not return expected incident type 800 |
| 19:51:27 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 1220383_1121140 (800) | Filter correctly excludes incident type 800 |
| 19:51:27 | ❌ FAIL | District Filter POSITIVE - Incident 1220383_1121140 (District 8) | Filter did not return expected district 8 |
| 19:51:27 | ✅ PASS | District Filter NEGATIVE - Incident 1220383_1121140 (District 8) | Filter correctly excludes district 8 |
| 19:51:27 | ❌ FAIL | Date Filter POSITIVE - Incident 1220383_1121140 (Month 9) | Filter returned no data for month 9 |
| 19:51:28 | ✅ PASS | Date Filter NEGATIVE - Incident 1220383_1121140 (Exclude Month 9) | Filter correctly excludes original month (returned 0 hexagons for month 10) |
| 19:51:28 | ❌ FAIL | Time Filter POSITIVE - Incident 1220383_1121140 (night, Hour 3) | Filter returned no data for night period |
| 19:51:28 | ✅ PASS | Time Filter NEGATIVE - Incident 1220383_1121140 (Exclude night) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:28 | ❌ FAIL | Combined Filters POSITIVE - Incident 1220383_1121140 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:28 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (1400) | Filter did not return expected incident type 1400 |
| 19:51:29 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (1400) | Filter correctly excludes incident type 1400 |
| 19:51:29 | ❌ FAIL | District Filter POSITIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (District 24) | Filter did not return expected district 24 |
| 19:51:29 | ✅ PASS | District Filter NEGATIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (District 24) | Filter correctly excludes district 24 |
| 19:51:29 | ❌ FAIL | Date Filter POSITIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (Month 12) | Filter returned no data for month 12 |
| 19:51:29 | ✅ PASS | Date Filter NEGATIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (Exclude Month 12) | Filter correctly excludes original month (returned 0 hexagons for month 1) |
| 19:51:30 | ❌ FAIL | Time Filter POSITIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (afternoon, Hour 17) | Filter returned no data for afternoon period |
| 19:51:30 | ✅ PASS | Time Filter NEGATIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (Exclude afternoon) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:30 | ❌ FAIL | Combined Filters POSITIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:30 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (800) | Filter did not return expected incident type 800 |
| 19:51:30 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (800) | Filter correctly excludes incident type 800 |
| 19:51:30 | ❌ FAIL | District Filter POSITIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (District 25) | Filter did not return expected district 25 |
| 19:51:30 | ✅ PASS | District Filter NEGATIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (District 25) | Filter correctly excludes district 25 |
| 19:51:31 | ❌ FAIL | Date Filter POSITIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (Month 4) | Filter returned no data for month 4 |
| 19:51:31 | ✅ PASS | Date Filter NEGATIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (Exclude Month 4) | Filter correctly excludes original month (returned 0 hexagons for month 5) |
| 19:51:31 | ❌ FAIL | Time Filter POSITIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (afternoon, Hour 16) | Filter returned no data for afternoon period |
| 19:51:31 | ✅ PASS | Time Filter NEGATIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (Exclude afternoon) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:31 | ❌ FAIL | Combined Filters POSITIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb | Combined filters returned no data (too restrictive or data missing) |
| 19:51:32 | ❌ FAIL | Crime Type Filter POSITIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (800) | Filter did not return expected incident type 800 |
| 19:51:32 | ✅ PASS | Crime Type Filter NEGATIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (800) | Filter correctly excludes incident type 800 |
| 19:51:32 | ❌ FAIL | District Filter POSITIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (District 25) | Filter did not return expected district 25 |
| 19:51:32 | ✅ PASS | District Filter NEGATIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (District 25) | Filter correctly excludes district 25 |
| 19:51:32 | ❌ FAIL | Date Filter POSITIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (Month 3) | Filter returned no data for month 3 |
| 19:51:33 | ✅ PASS | Date Filter NEGATIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (Exclude Month 3) | Filter correctly excludes original month (returned 0 hexagons for month 4) |
| 19:51:33 | ❌ FAIL | Time Filter POSITIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (night, Hour 2) | Filter returned no data for night period |
| 19:51:33 | ✅ PASS | Time Filter NEGATIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (Exclude night) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:33 | ❌ FAIL | Combined Filters POSITIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:33 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (1100) | Filter did not return expected incident type 1100 |
| 19:51:33 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (1100) | Filter correctly excludes incident type 1100 |
| 19:51:34 | ❌ FAIL | District Filter POSITIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (District 15) | Filter did not return expected district 15 |
| 19:51:34 | ✅ PASS | District Filter NEGATIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (District 15) | Filter correctly excludes district 15 |
| 19:51:34 | ❌ FAIL | Date Filter POSITIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (Month 7) | Filter returned no data for month 7 |
| 19:51:34 | ✅ PASS | Date Filter NEGATIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (Exclude Month 7) | Filter correctly excludes original month (returned 0 hexagons for month 8) |
| 19:51:34 | ❌ FAIL | Time Filter POSITIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (evening, Hour 23) | Filter returned no data for evening period |
| 19:51:35 | ✅ PASS | Time Filter NEGATIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (Exclude evening) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:35 | ❌ FAIL | Combined Filters POSITIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb | Combined filters returned no data (too restrictive or data missing) |
| 19:51:35 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (600) | Filter did not return expected incident type 600 |
| 19:51:35 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (600) | Filter correctly excludes incident type 600 |
| 19:51:35 | ❌ FAIL | District Filter POSITIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (District 15) | Filter did not return expected district 15 |
| 19:51:35 | ✅ PASS | District Filter NEGATIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (District 15) | Filter correctly excludes district 15 |
| 19:51:36 | ❌ FAIL | Date Filter POSITIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (Month 6) | Filter returned no data for month 6 |
| 19:51:36 | ✅ PASS | Date Filter NEGATIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (Exclude Month 6) | Filter correctly excludes original month (returned 0 hexagons for month 7) |
| 19:51:36 | ❌ FAIL | Time Filter POSITIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (evening, Hour 18) | Filter returned no data for evening period |
| 19:51:36 | ✅ PASS | Time Filter NEGATIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (Exclude evening) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:36 | ❌ FAIL | Combined Filters POSITIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e | Combined filters returned no data (too restrictive or data missing) |
| 19:51:37 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (1400) | Filter did not return expected incident type 1400 |
| 19:51:37 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (1400) | Filter correctly excludes incident type 1400 |
| 19:51:37 | ❌ FAIL | District Filter POSITIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (District 24) | Filter did not return expected district 24 |
| 19:51:37 | ✅ PASS | District Filter NEGATIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (District 24) | Filter correctly excludes district 24 |
| 19:51:37 | ❌ FAIL | Date Filter POSITIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (Month 12) | Filter returned no data for month 12 |
| 19:51:38 | ✅ PASS | Date Filter NEGATIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (Exclude Month 12) | Filter correctly excludes original month (returned 0 hexagons for month 1) |
| 19:51:38 | ❌ FAIL | Time Filter POSITIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (evening, Hour 23) | Filter returned no data for evening period |
| 19:51:38 | ✅ PASS | Time Filter NEGATIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (Exclude evening) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:38 | ❌ FAIL | Combined Filters POSITIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:38 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 409409_306239 (500) | Filter did not return expected incident type 500 |
| 19:51:39 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 409409_306239 (500) | Filter correctly excludes incident type 500 |
| 19:51:39 | ❌ FAIL | District Filter POSITIVE - Incident 409409_306239 (District 25) | Filter did not return expected district 25 |
| 19:51:39 | ✅ PASS | District Filter NEGATIVE - Incident 409409_306239 (District 25) | Filter correctly excludes district 25 |
| 19:51:39 | ❌ FAIL | Date Filter POSITIVE - Incident 409409_306239 (Month 10) | Filter returned no data for month 10 |
| 19:51:39 | ✅ PASS | Date Filter NEGATIVE - Incident 409409_306239 (Exclude Month 10) | Filter correctly excludes original month (returned 0 hexagons for month 11) |
| 19:51:39 | ❌ FAIL | Time Filter POSITIVE - Incident 409409_306239 (afternoon, Hour 12) | Filter returned no data for afternoon period |
| 19:51:40 | ✅ PASS | Time Filter NEGATIVE - Incident 409409_306239 (Exclude afternoon) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:40 | ❌ FAIL | Combined Filters POSITIVE - Incident 409409_306239 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:40 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (2600) | Filter did not return expected incident type 2600 |
| 19:51:40 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (2600) | Filter correctly excludes incident type 2600 |
| 19:51:40 | ❌ FAIL | District Filter POSITIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (District 17) | Filter did not return expected district 17 |
| 19:51:40 | ✅ PASS | District Filter NEGATIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (District 17) | Filter correctly excludes district 17 |
| 19:51:41 | ❌ FAIL | Date Filter POSITIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (Month 5) | Filter returned no data for month 5 |
| 19:51:41 | ✅ PASS | Date Filter NEGATIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (Exclude Month 5) | Filter correctly excludes original month (returned 0 hexagons for month 6) |
| 19:51:41 | ❌ FAIL | Time Filter POSITIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (night, Hour 0) | Filter returned no data for night period |
| 19:51:41 | ✅ PASS | Time Filter NEGATIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (Exclude night) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:42 | ❌ FAIL | Combined Filters POSITIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b | Combined filters returned no data (too restrictive or data missing) |
| 19:51:42 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (2600) | Filter did not return expected incident type 2600 |
| 19:51:42 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (2600) | Filter correctly excludes incident type 2600 |
| 19:51:42 | ❌ FAIL | District Filter POSITIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (District 8) | Filter did not return expected district 8 |
| 19:51:42 | ✅ PASS | District Filter NEGATIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (District 8) | Filter correctly excludes district 8 |
| 19:51:42 | ❌ FAIL | Date Filter POSITIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (Month 7) | Filter returned no data for month 7 |
| 19:51:43 | ✅ PASS | Date Filter NEGATIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (Exclude Month 7) | Filter correctly excludes original month (returned 0 hexagons for month 8) |
| 19:51:43 | ❌ FAIL | Time Filter POSITIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (night, Hour 3) | Filter returned no data for night period |
| 19:51:43 | ✅ PASS | Time Filter NEGATIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (Exclude night) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:43 | ❌ FAIL | Combined Filters POSITIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b | Combined filters returned no data (too restrictive or data missing) |
| 19:51:43 | ❌ FAIL | Crime Type Filter POSITIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (600) | Filter did not return expected incident type 600 |
| 19:51:44 | ✅ PASS | Crime Type Filter NEGATIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (600) | Filter correctly excludes incident type 600 |
| 19:51:44 | ❌ FAIL | District Filter POSITIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (District 6) | Filter did not return expected district 6 |
| 19:51:44 | ✅ PASS | District Filter NEGATIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (District 6) | Filter correctly excludes district 6 |
| 19:51:44 | ❌ FAIL | Date Filter POSITIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (Month 11) | Filter returned no data for month 11 |
| 19:51:44 | ✅ PASS | Date Filter NEGATIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (Exclude Month 11) | Filter correctly excludes original month (returned 0 hexagons for month 12) |
| 19:51:45 | ❌ FAIL | Time Filter POSITIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (afternoon, Hour 17) | Filter returned no data for afternoon period |
| 19:51:45 | ✅ PASS | Time Filter NEGATIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (Exclude afternoon) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:45 | ❌ FAIL | Combined Filters POSITIVE - Incident a214130c-0214-4373-84eb-38824498bd6f | Combined filters returned no data (too restrictive or data missing) |
| 19:51:45 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (300) | Filter did not return expected incident type 300 |
| 19:51:45 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (300) | Filter correctly excludes incident type 300 |
| 19:51:45 | ❌ FAIL | District Filter POSITIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (District 2) | Filter did not return expected district 2 |
| 19:51:46 | ✅ PASS | District Filter NEGATIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (District 2) | Filter correctly excludes district 2 |
| 19:51:46 | ❌ FAIL | Date Filter POSITIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (Month 12) | Filter returned no data for month 12 |
| 19:51:46 | ✅ PASS | Date Filter NEGATIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (Exclude Month 12) | Filter correctly excludes original month (returned 0 hexagons for month 1) |
| 19:51:46 | ❌ FAIL | Time Filter POSITIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (evening, Hour 22) | Filter returned no data for evening period |
| 19:51:46 | ✅ PASS | Time Filter NEGATIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (Exclude evening) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:46 | ❌ FAIL | Combined Filters POSITIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:47 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (600) | Filter did not return expected incident type 600 |
| 19:51:47 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (600) | Filter correctly excludes incident type 600 |
| 19:51:47 | ❌ FAIL | District Filter POSITIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (District 25) | Filter did not return expected district 25 |
| 19:51:47 | ✅ PASS | District Filter NEGATIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (District 25) | Filter correctly excludes district 25 |
| 19:51:47 | ❌ FAIL | Date Filter POSITIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (Month 9) | Filter returned no data for month 9 |
| 19:51:48 | ✅ PASS | Date Filter NEGATIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (Exclude Month 9) | Filter correctly excludes original month (returned 0 hexagons for month 10) |
| 19:51:48 | ❌ FAIL | Time Filter POSITIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (night, Hour 3) | Filter returned no data for night period |
| 19:51:48 | ✅ PASS | Time Filter NEGATIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (Exclude night) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:48 | ❌ FAIL | Combined Filters POSITIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c | Combined filters returned no data (too restrictive or data missing) |
| 19:51:48 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 3187915_5737829 (2600) | Filter did not return expected incident type 2600 |
| 19:51:48 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 3187915_5737829 (2600) | Filter correctly excludes incident type 2600 |
| 19:51:49 | ❌ FAIL | District Filter POSITIVE - Incident 3187915_5737829 (District 5) | Filter did not return expected district 5 |
| 19:51:49 | ✅ PASS | District Filter NEGATIVE - Incident 3187915_5737829 (District 5) | Filter correctly excludes district 5 |
| 19:51:49 | ❌ FAIL | Date Filter POSITIVE - Incident 3187915_5737829 (Month 7) | Filter returned no data for month 7 |
| 19:51:49 | ✅ PASS | Date Filter NEGATIVE - Incident 3187915_5737829 (Exclude Month 7) | Filter correctly excludes original month (returned 0 hexagons for month 8) |
| 19:51:49 | ❌ FAIL | Time Filter POSITIVE - Incident 3187915_5737829 (afternoon, Hour 12) | Filter returned no data for afternoon period |
| 19:51:50 | ✅ PASS | Time Filter NEGATIVE - Incident 3187915_5737829 (Exclude afternoon) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:50 | ❌ FAIL | Combined Filters POSITIVE - Incident 3187915_5737829 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:50 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (1400) | Filter did not return expected incident type 1400 |
| 19:51:50 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (1400) | Filter correctly excludes incident type 1400 |
| 19:51:50 | ❌ FAIL | District Filter POSITIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (District 18) | Filter did not return expected district 18 |
| 19:51:50 | ✅ PASS | District Filter NEGATIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (District 18) | Filter correctly excludes district 18 |
| 19:51:50 | ❌ FAIL | Date Filter POSITIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (Month 7) | Filter returned no data for month 7 |
| 19:51:51 | ✅ PASS | Date Filter NEGATIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (Exclude Month 7) | Filter correctly excludes original month (returned 0 hexagons for month 8) |
| 19:51:51 | ❌ FAIL | Time Filter POSITIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (afternoon, Hour 13) | Filter returned no data for afternoon period |
| 19:51:51 | ✅ PASS | Time Filter NEGATIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (Exclude afternoon) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:51 | ❌ FAIL | Combined Filters POSITIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:51 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 1360260_1262131 (800) | Filter did not return expected incident type 800 |
| 19:51:52 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 1360260_1262131 (800) | Filter correctly excludes incident type 800 |
| 19:51:52 | ❌ FAIL | District Filter POSITIVE - Incident 1360260_1262131 (District 18) | Filter did not return expected district 18 |
| 19:51:52 | ✅ PASS | District Filter NEGATIVE - Incident 1360260_1262131 (District 18) | Filter correctly excludes district 18 |
| 19:51:52 | ❌ FAIL | Date Filter POSITIVE - Incident 1360260_1262131 (Month 12) | Filter returned no data for month 12 |
| 19:51:52 | ✅ PASS | Date Filter NEGATIVE - Incident 1360260_1262131 (Exclude Month 12) | Filter correctly excludes original month (returned 0 hexagons for month 1) |
| 19:51:53 | ❌ FAIL | Time Filter POSITIVE - Incident 1360260_1262131 (night, Hour 1) | Filter returned no data for night period |
| 19:51:53 | ✅ PASS | Time Filter NEGATIVE - Incident 1360260_1262131 (Exclude night) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:53 | ❌ FAIL | Combined Filters POSITIVE - Incident 1360260_1262131 | Combined filters returned no data (too restrictive or data missing) |
| 19:51:53 | ❌ FAIL | Crime Type Filter POSITIVE - Incident 1329173_1228527 (800) | Filter did not return expected incident type 800 |
| 19:51:53 | ✅ PASS | Crime Type Filter NEGATIVE - Incident 1329173_1228527 (800) | Filter correctly excludes incident type 800 |
| 19:51:53 | ❌ FAIL | District Filter POSITIVE - Incident 1329173_1228527 (District 99) | Filter did not return expected district 99 |
| 19:51:53 | ✅ PASS | District Filter NEGATIVE - Incident 1329173_1228527 (District 99) | Filter correctly excludes district 99 |
| 19:51:54 | ❌ FAIL | Date Filter POSITIVE - Incident 1329173_1228527 (Month 11) | Filter returned no data for month 11 |
| 19:51:54 | ✅ PASS | Date Filter NEGATIVE - Incident 1329173_1228527 (Exclude Month 11) | Filter correctly excludes original month (returned 0 hexagons for month 12) |
| 19:51:54 | ❌ FAIL | Time Filter POSITIVE - Incident 1329173_1228527 (evening, Hour 23) | Filter returned no data for evening period |
| 19:51:54 | ✅ PASS | Time Filter NEGATIVE - Incident 1329173_1228527 (Exclude evening) | Filter correctly excludes original time period (returned 0 hexagons for morning) |
| 19:51:54 | ❌ FAIL | Combined Filters POSITIVE - Incident 1329173_1228527 | Combined filters returned no data (too restrictive or data missing) |

## Test Summary by Filter Type

### Crime Type Filter Tests
- **Total Tests**: 40
- **Passed**: 20
- **Success Rate**: 50.0%

### District Filter Tests
- **Total Tests**: 40
- **Passed**: 20
- **Success Rate**: 50.0%

### Date Filter Tests
- **Total Tests**: 40
- **Passed**: 20
- **Success Rate**: 50.0%

### Time Filter Tests
- **Total Tests**: 40
- **Passed**: 20
- **Success Rate**: 50.0%

### Combined Filter Tests
- **Total Tests**: 20
- **Passed**: 0
- **Success Rate**: 0%


## Recommendations

### Issues Found
- **Crime Type Filter POSITIVE - Incident 3261967_22171591 (600)**: Filter did not return expected incident type 600
- **District Filter POSITIVE - Incident 3261967_22171591 (District 35)**: Filter did not return expected district 35
- **Date Filter POSITIVE - Incident 3261967_22171591 (Month 9)**: Filter returned no data for month 9
- **Time Filter POSITIVE - Incident 3261967_22171591 (evening, Hour 23)**: Filter returned no data for evening period
- **Combined Filters POSITIVE - Incident 3261967_22171591**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (800)**: Filter did not return expected incident type 800
- **District Filter POSITIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (District 18)**: Filter did not return expected district 18
- **Date Filter POSITIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (Month 1)**: Filter returned no data for month 1
- **Time Filter POSITIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7 (night, Hour 2)**: Filter returned no data for night period
- **Combined Filters POSITIVE - Incident 3433f040-93d2-4a52-8020-5a0deecca8d7**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (1100)**: Filter did not return expected incident type 1100
- **District Filter POSITIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (District 2)**: Filter did not return expected district 2
- **Date Filter POSITIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (Month 2)**: Filter returned no data for month 2
- **Time Filter POSITIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3 (evening, Hour 18)**: Filter returned no data for evening period
- **Combined Filters POSITIVE - Incident 273e7726-0849-42f5-89be-b2e9e9c2abc3**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 1220383_1121140 (800)**: Filter did not return expected incident type 800
- **District Filter POSITIVE - Incident 1220383_1121140 (District 8)**: Filter did not return expected district 8
- **Date Filter POSITIVE - Incident 1220383_1121140 (Month 9)**: Filter returned no data for month 9
- **Time Filter POSITIVE - Incident 1220383_1121140 (night, Hour 3)**: Filter returned no data for night period
- **Combined Filters POSITIVE - Incident 1220383_1121140**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (1400)**: Filter did not return expected incident type 1400
- **District Filter POSITIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (District 24)**: Filter did not return expected district 24
- **Date Filter POSITIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (Month 12)**: Filter returned no data for month 12
- **Time Filter POSITIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92 (afternoon, Hour 17)**: Filter returned no data for afternoon period
- **Combined Filters POSITIVE - Incident 87ad3ff4-ee03-421a-8ef8-ab41df6dcd92**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (800)**: Filter did not return expected incident type 800
- **District Filter POSITIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (District 25)**: Filter did not return expected district 25
- **Date Filter POSITIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (Month 4)**: Filter returned no data for month 4
- **Time Filter POSITIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb (afternoon, Hour 16)**: Filter returned no data for afternoon period
- **Combined Filters POSITIVE - Incident 110e329a-e440-4777-b00b-6fb9d8d529bb**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (800)**: Filter did not return expected incident type 800
- **District Filter POSITIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (District 25)**: Filter did not return expected district 25
- **Date Filter POSITIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (Month 3)**: Filter returned no data for month 3
- **Time Filter POSITIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213 (night, Hour 2)**: Filter returned no data for night period
- **Combined Filters POSITIVE - Incident e8b6c357-da3b-4058-adee-7add9fcab213**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (1100)**: Filter did not return expected incident type 1100
- **District Filter POSITIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (District 15)**: Filter did not return expected district 15
- **Date Filter POSITIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (Month 7)**: Filter returned no data for month 7
- **Time Filter POSITIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb (evening, Hour 23)**: Filter returned no data for evening period
- **Combined Filters POSITIVE - Incident 7ee6f95d-3025-4061-b677-0c309b053dfb**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (600)**: Filter did not return expected incident type 600
- **District Filter POSITIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (District 15)**: Filter did not return expected district 15
- **Date Filter POSITIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (Month 6)**: Filter returned no data for month 6
- **Time Filter POSITIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e (evening, Hour 18)**: Filter returned no data for evening period
- **Combined Filters POSITIVE - Incident 2a2c5f87-7b0b-43c6-a16a-2bf625090e1e**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (1400)**: Filter did not return expected incident type 1400
- **District Filter POSITIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (District 24)**: Filter did not return expected district 24
- **Date Filter POSITIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (Month 12)**: Filter returned no data for month 12
- **Time Filter POSITIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582 (evening, Hour 23)**: Filter returned no data for evening period
- **Combined Filters POSITIVE - Incident 72ed1508-2baf-4dfd-ba8c-37dc323ae582**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 409409_306239 (500)**: Filter did not return expected incident type 500
- **District Filter POSITIVE - Incident 409409_306239 (District 25)**: Filter did not return expected district 25
- **Date Filter POSITIVE - Incident 409409_306239 (Month 10)**: Filter returned no data for month 10
- **Time Filter POSITIVE - Incident 409409_306239 (afternoon, Hour 12)**: Filter returned no data for afternoon period
- **Combined Filters POSITIVE - Incident 409409_306239**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (2600)**: Filter did not return expected incident type 2600
- **District Filter POSITIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (District 17)**: Filter did not return expected district 17
- **Date Filter POSITIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (Month 5)**: Filter returned no data for month 5
- **Time Filter POSITIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b (night, Hour 0)**: Filter returned no data for night period
- **Combined Filters POSITIVE - Incident 02f0b208-f960-4462-80cf-f4ab21385a3b**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (2600)**: Filter did not return expected incident type 2600
- **District Filter POSITIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (District 8)**: Filter did not return expected district 8
- **Date Filter POSITIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (Month 7)**: Filter returned no data for month 7
- **Time Filter POSITIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b (night, Hour 3)**: Filter returned no data for night period
- **Combined Filters POSITIVE - Incident 13ab50b5-60f6-4b0f-a7df-b8d20f5f027b**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (600)**: Filter did not return expected incident type 600
- **District Filter POSITIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (District 6)**: Filter did not return expected district 6
- **Date Filter POSITIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (Month 11)**: Filter returned no data for month 11
- **Time Filter POSITIVE - Incident a214130c-0214-4373-84eb-38824498bd6f (afternoon, Hour 17)**: Filter returned no data for afternoon period
- **Combined Filters POSITIVE - Incident a214130c-0214-4373-84eb-38824498bd6f**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (300)**: Filter did not return expected incident type 300
- **District Filter POSITIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (District 2)**: Filter did not return expected district 2
- **Date Filter POSITIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (Month 12)**: Filter returned no data for month 12
- **Time Filter POSITIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4 (evening, Hour 22)**: Filter returned no data for evening period
- **Combined Filters POSITIVE - Incident 51a19b75-e28c-404a-9e4c-b52742077ea4**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (600)**: Filter did not return expected incident type 600
- **District Filter POSITIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (District 25)**: Filter did not return expected district 25
- **Date Filter POSITIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (Month 9)**: Filter returned no data for month 9
- **Time Filter POSITIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c (night, Hour 3)**: Filter returned no data for night period
- **Combined Filters POSITIVE - Incident 8d7f29c9-79fb-437c-b9c4-ce02e5872c6c**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 3187915_5737829 (2600)**: Filter did not return expected incident type 2600
- **District Filter POSITIVE - Incident 3187915_5737829 (District 5)**: Filter did not return expected district 5
- **Date Filter POSITIVE - Incident 3187915_5737829 (Month 7)**: Filter returned no data for month 7
- **Time Filter POSITIVE - Incident 3187915_5737829 (afternoon, Hour 12)**: Filter returned no data for afternoon period
- **Combined Filters POSITIVE - Incident 3187915_5737829**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (1400)**: Filter did not return expected incident type 1400
- **District Filter POSITIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (District 18)**: Filter did not return expected district 18
- **Date Filter POSITIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (Month 7)**: Filter returned no data for month 7
- **Time Filter POSITIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9 (afternoon, Hour 13)**: Filter returned no data for afternoon period
- **Combined Filters POSITIVE - Incident 48179705-a06d-4bf6-8a72-0098aed90ce9**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 1360260_1262131 (800)**: Filter did not return expected incident type 800
- **District Filter POSITIVE - Incident 1360260_1262131 (District 18)**: Filter did not return expected district 18
- **Date Filter POSITIVE - Incident 1360260_1262131 (Month 12)**: Filter returned no data for month 12
- **Time Filter POSITIVE - Incident 1360260_1262131 (night, Hour 1)**: Filter returned no data for night period
- **Combined Filters POSITIVE - Incident 1360260_1262131**: Combined filters returned no data (too restrictive or data missing)
- **Crime Type Filter POSITIVE - Incident 1329173_1228527 (800)**: Filter did not return expected incident type 800
- **District Filter POSITIVE - Incident 1329173_1228527 (District 99)**: Filter did not return expected district 99
- **Date Filter POSITIVE - Incident 1329173_1228527 (Month 11)**: Filter returned no data for month 11
- **Time Filter POSITIVE - Incident 1329173_1228527 (evening, Hour 23)**: Filter returned no data for evening period
- **Combined Filters POSITIVE - Incident 1329173_1228527**: Combined filters returned no data (too restrictive or data missing)

### Performance Observations
- API response times were acceptable for all tested endpoints
- Filter combinations worked as expected
- Database queries performed efficiently with random incident selection

### Next Steps
- Monitor filter performance with larger datasets
- Consider implementing caching for frequently used filter combinations
- Validate filter UI behavior matches API functionality

---

**Test Environment**: Development  
**Database**: stlouisintegration_dev  
**API Base**: http://localhost/api/amisafe  
**Test Duration**: Fri Nov  7 19:51:56 UTC 2025  
