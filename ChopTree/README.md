# ChopTree
The simple woodcutter plugin for PocketMine-MP

## Permissions
| Permission | Default | Description |
| :------: | :---: | :---------: |
| `ChopTree.break` | `true` | Allows player to chop the tree by breaking the stump |
| `ChopTree.doubleTouch` | `true` | Allows player to chop the tree by double-touching the stump |

## Configuration
### General configuration
| Key | Description | Available type | Default value |
| :---: | :------: | :---: | :---: |
| `maxWorldHeight` | The max height of world | `int` | `128` |

### `break` configuration
| Key | Description | Available type | Default value |
| :---: | :------: | :---: | :---: |
| `enabled` | Sets the method available | `boolean` | `true` |
| `tools` | Sets available tools for this method | `int[]` | `[275, 258, 286, 279]` |
| `plantSaplingAfter` | Sets the weather to plant the sapling after | `boolean` | `true` |
| `costPerBlock` | Sets the weather to calculate the cost by the count of broken woods | `boolean` | `false` |
| `cost` | The cost of method | `int` | `10` |

### `doubleTouch` configuration
| Key | Description | Available type | Default value |
| :---: | :------: | :---: | :---: |
| `enabled` | Sets the method available | `boolean` | `true` |
| `tools` | Sets available tools for this method | `int[]` | `[286, 279]` |
| `plantSaplingAfter` | Sets the weather to plant the sapling after | `boolean` | `true` |
| `costPerBlock` | Sets the weather to calculate the cost by the count of broken woods | `boolean` | `false` |
| `cost` | The cost of method | `int` | `20` |

# Screenshots
![First](http://i.imgur.com/q4ouXGi.png)

![Second](http://i.imgur.com/HUpKAyE.png)

# License
```
Copyright 2015 ChalkPE

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```